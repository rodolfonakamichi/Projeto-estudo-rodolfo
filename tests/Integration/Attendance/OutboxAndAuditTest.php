<?php

declare(strict_types=1);

namespace App\Tests\Integration\Attendance;

use App\Application\Attendance\Command\AddItemToCommand;
use App\Application\Attendance\Command\CancelCommandItem;
use App\Application\Attendance\Command\OpenCommand;
use App\Application\Audit\AuditTrail;
use App\Application\Catalog\Product\CreateProduct;
use App\Application\Catalog\Product\VariantInput;
use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Application\Shared\Event\OutboxRelay;
use App\Application\Shared\Event\RecordedEvent;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandItemId;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * M3.3 (doc 05): o evento é gravado na MESMA transação da comanda; o relay
 * despacha em ordem e a auditoria registra as ações sensíveis — aceite do
 * doc 07: "cancelar item gera CommandItemCancelled + audit_log".
 */
final class OutboxAndAuditTest extends KernelTestCase
{
    private string $companyId;
    private string $branchId;
    private string $waiterId;
    private string $commandId;
    private string $itemId;

    protected function setUp(): void
    {
        $this->companyId = CompanyId::generate()->toString();
        $this->branchId = BranchId::generate()->toString();
        $this->waiterId = UserId::generate()->toString();
        $managerId = UserId::generate()->toString();
        $productId = ProductId::generate()->toString();
        $variantId = ProductVariantId::generate()->toString();
        $this->commandId = CommandId::generate()->toString();
        $this->itemId = CommandItemId::generate()->toString();

        $bus = $this->bus();
        $bus->dispatch(new RegisterCompany($this->companyId, 'Bar do Zé', 'bar-do-ze', $this->branchId, 'Matriz'));
        $bus->dispatch(new RegisterUser($managerId, $this->companyId, 'Gil', 'gil@bar.com', 'segredo123', [new RoleAssignment('MANAGER')]));
        $bus->dispatch(new RegisterUser($this->waiterId, $this->companyId, 'Ana', 'ana@bar.com', 'segredo123', [new RoleAssignment('WAITER', $this->branchId)]));
        $bus->dispatch(new CreateProduct($productId, $managerId, $this->branchId, 'Chopp', [new VariantInput($variantId, '500ml', 1800)]));

        $bus->dispatch(new OpenCommand($this->commandId, $this->waiterId, $this->branchId, 'BALCAO', 'João'));
        $bus->dispatch(new AddItemToCommand($this->commandId, $this->itemId, $this->waiterId, $productId, $variantId, 2));
        $bus->dispatch(new CancelCommandItem($this->commandId, $this->itemId, $this->waiterId, 'chopp quente'));
    }

    public function test_eventos_gravados_junto_com_a_comanda(): void
    {
        $events = $this->db()->fetchAllAssociative(
            'SELECT * FROM domain_events WHERE aggregate_id = ? ORDER BY id',
            [$this->commandId],
        );

        self::assertSame(['CommandOpened', 'CommandItemAdded', 'CommandItemCancelled'], array_column($events, 'name'));
        self::assertSame(['command'], array_values(array_unique(array_column($events, 'aggregate_type'))));
        self::assertSame($this->branchId, $events[0]['branch_id']);
        self::assertNull($events[0]['published_at'], 'ainda não despachado');

        $cancelled = json_decode((string) $events[2]['payload'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['Chopp 500ml', 2, 'chopp quente', $this->waiterId], [$cancelled['itemName'], $cancelled['quantity'], $cancelled['reason'], $cancelled['actorId']]);
    }

    public function test_caso_de_uso_recusado_nao_grava_evento(): void
    {
        $before = $this->pendingCount();

        try {
            $this->bus()->dispatch(new CancelCommandItem($this->commandId, $this->itemId, $this->waiterId, 'de novo'));
            self::fail('cancelou duas vezes');
        } catch (\DomainException) {
        }

        self::assertSame($before, $this->pendingCount());
    }

    /** Aceite M3 (doc 07): cancelar item → CommandItemCancelled + audit_log. */
    public function test_relay_despacha_em_ordem_e_audita_as_acoes_sensiveis(): void
    {
        $tester = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:outbox:publish'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('evento(s) despachado(s)', $tester->getDisplay());
        self::assertSame(0, $this->pendingCount());

        $audit = $this->db()->fetchAllAssociative('SELECT * FROM audit_logs WHERE target_id = ? ORDER BY created_at, action', [$this->commandId]);
        self::assertSame(['command.open', 'command.item.cancel'], array_column($audit, 'action'), 'lançar item não é auditado');
        self::assertSame('Ana', $audit[1]['actor_name']);
        self::assertSame('Comanda #1: 2× Chopp 500ml cancelado — chopp quente', $audit[1]['summary']);
        self::assertSame($this->branchId, $audit[1]['branch_id']);

        // rodar de novo não despacha nada nem duplica a auditoria
        $result = self::getContainer()->get(OutboxRelay::class)->relay(100);
        self::assertSame(0, $result->published);
        self::assertSame(2, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM audit_logs WHERE target_id = ?', [$this->commandId]));
    }

    public function test_auditoria_e_idempotente_pelo_id_do_evento(): void
    {
        $trail = self::getContainer()->get(AuditTrail::class);
        $event = new RecordedEvent('01JAAAAAAAAAAAAAAAAAAAAAAA', 'CommandCancelled', 'command', $this->commandId, $this->companyId, $this->branchId, [
            'displayNumber' => 1, 'reason' => 'teste', 'actorId' => $this->waiterId,
        ], new \DateTimeImmutable());

        $trail($event);
        $trail($event);

        self::assertSame(1, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM audit_logs WHERE id = '01JAAAAAAAAAAAAAAAAAAAAAAA'"));
    }

    private function pendingCount(): int
    {
        return (int) $this->db()->fetchOne('SELECT COUNT(*) FROM domain_events WHERE published_at IS NULL');
    }

    private function bus(): CommandBus
    {
        return self::getContainer()->get(CommandBus::class);
    }

    private function db(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
