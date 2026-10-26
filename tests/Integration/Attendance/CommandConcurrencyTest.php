<?php

declare(strict_types=1);

namespace App\Tests\Integration\Attendance;

use App\Application\Attendance\Command\DisplayNumberSequence;
use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Attendance\BindTarget;
use App\Domain\Attendance\BindType;
use App\Domain\Attendance\Command;
use App\Domain\Attendance\CommandId;
use App\Domain\Attendance\CommandRepository;
use App\Domain\Attendance\LocationId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * As garantias que NÃO dependem do caso de uso: se dois garçons passarem pela
 * checagem ao mesmo tempo, o banco ainda barra (UNIQUE → 409 na API), e o
 * contador nunca repete número.
 */
final class CommandConcurrencyTest extends KernelTestCase
{
    private CompanyId $companyId;
    private BranchId $branchId;
    private UserId $userId;

    protected function setUp(): void
    {
        $this->companyId = CompanyId::generate();
        $this->branchId = BranchId::generate();
        $this->userId = UserId::generate();
        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($this->companyId->toString(), 'Bar do Zé', 'bar-do-ze', $this->branchId->toString(), 'Matriz'));
        $bus->dispatch(new RegisterUser($this->userId->toString(), $this->companyId->toString(), 'Ana', 'ana@bar.com', 'segredo123', [new RoleAssignment('WAITER')]));
    }

    public function test_banco_barra_segunda_comanda_aberta_na_mesma_mesa(): void
    {
        $table = BindTarget::of(BindType::Table, LocationId::generate()->toString());
        $commands = self::getContainer()->get(CommandRepository::class);
        $commands->save($this->open($table, 1));
        $commands->save($this->open($table, 2));

        $this->expectException(UniqueConstraintViolationException::class);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    public function test_numero_do_dia_nao_repete_e_recomeca_em_outra_data(): void
    {
        $numbers = self::getContainer()->get(DisplayNumberSequence::class);
        $friday = new \DateTimeImmutable('2026-10-23');

        self::assertSame(1, $numbers->next($this->companyId, $this->branchId, $friday));
        self::assertSame(2, $numbers->next($this->companyId, $this->branchId, $friday));
        self::assertSame(1, $numbers->next($this->companyId, $this->branchId, new \DateTimeImmutable('2026-10-24')));
        self::assertSame(3, $numbers->next($this->companyId, $this->branchId, $friday));
    }

    private function open(BindTarget $bind, int $number): Command
    {
        $now = new \DateTimeImmutable();

        return Command::open(CommandId::generate(), $this->companyId, $this->branchId, $bind, $number, new \DateTimeImmutable('2026-10-26'), $this->userId, $now);
    }
}
