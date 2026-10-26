<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Http\Catalog;

use App\Application\Catalog\CatalogQuery;
use App\Application\Identity\RegisterCompany\RegisterCompany;
use App\Application\Identity\RegisterUser\RegisterUser;
use App\Application\Identity\RegisterUser\RoleAssignment;
use App\Application\Shared\Bus\CommandBus;
use App\Domain\Catalog\ProductId;
use App\Domain\Catalog\ProductVariantId;
use App\Domain\Identity\BranchId;
use App\Domain\Identity\CompanyId;
use App\Domain\Identity\UserId;
use App\Tests\Presentation\Http\JsonApiClient;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Aceite M2.4 (doc 07): `q=cho` acha o Chopp em < 50 ms com 1k produtos.
 *
 * #[SkipDatabaseRollback]: o InnoDB só atualiza o índice FULLTEXT no COMMIT —
 * dentro da transação que o DAMA desfaz no fim do teste, a busca não veria
 * nada. Aqui os dados são commitados de verdade e apagados no tearDown.
 */
#[SkipDatabaseRollback]
final class CatalogSearchTest extends WebTestCase
{
    use JsonApiClient;

    private const PRODUCTS = 1000;

    private CompanyId $companyId;
    private BranchId $branchId;
    private string $slug;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        $this->companyId = CompanyId::generate();
        $this->branchId = BranchId::generate();
        $this->slug = 'busca-'.strtolower(substr($this->companyId->toString(), -8));

        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch(new RegisterCompany($this->companyId->toString(), 'Bar da Busca', $this->slug, $this->branchId->toString(), 'Matriz'));
        $bus->dispatch(new RegisterUser(UserId::generate()->toString(), $this->companyId->toString(), 'Ana', 'ana@busca.com', 'segredo123', [new RoleAssignment('WAITER', $this->branchId->toString())]));

        $this->insertProducts();
    }

    protected function tearDown(): void
    {
        // products antes: a FK products→categories/stations é RESTRICT; o resto cai em cascata com a empresa
        $db = self::getContainer()->get(Connection::class);
        $db->executeStatement('DELETE FROM products WHERE company_id = ?', [$this->companyId->toString()]);
        $db->executeStatement('DELETE FROM companies WHERE id = ?', [$this->companyId->toString()]);

        parent::tearDown();
    }

    public function test_busca_por_prefixo_pela_api(): void
    {
        $token = $this->login($this->slug, 'ana@busca.com');

        $found = $this->json('GET', "/api/branches/{$this->branchId}/catalog/search?q=cho", token: $token);
        self::assertResponseIsSuccessful();
        self::assertSame(['Chopp'], array_column($found['products'], 'name'));
        self::assertSame(['300ml'], array_column($found['products'][0]['variants'], 'name'));

        $tooShort = $this->json('GET', "/api/branches/{$this->branchId}/catalog/search?q=ch", token: $token);
        self::assertSame([], $tooShort['products'], 'menos de 3 letras não consulta');

        $inactive = $this->json('GET', "/api/branches/{$this->branchId}/catalog/search?q=descontinuado", token: $token);
        self::assertSame([], $inactive['products'], 'produto inativo não aparece');
    }

    public function test_aceite_menos_de_50ms_com_1k_produtos_usando_o_indice(): void
    {
        $catalog = self::getContainer()->get(CatalogQuery::class);
        $catalog->search($this->companyId, $this->branchId, 'cho'); // aquece conexão e cache de consulta

        $timings = [];
        for ($run = 0; $run < 5; ++$run) {
            $start = hrtime(true);
            $result = $catalog->search($this->companyId, $this->branchId, 'cho');
            $timings[] = (hrtime(true) - $start) / 1e6;
        }
        sort($timings);

        self::assertSame('Chopp', $result[0]->name);
        self::assertLessThan(50, $timings[2], \sprintf('mediana %.1f ms', $timings[2]));

        $plan = self::getContainer()->get(Connection::class)->fetchAssociative(
            "EXPLAIN SELECT id FROM products WHERE company_id = ? AND branch_id = ? AND MATCH (name) AGAINST ('+cho*' IN BOOLEAN MODE)",
            [$this->companyId->toString(), $this->branchId->toString()],
        );
        self::assertSame('ft_products_name', $plan['key'] ?? null, 'a busca usa o índice FULLTEXT');
    }

    /** 1k produtos em lote via SQL (o caso de uso levaria segundos — aqui o alvo é a leitura). */
    private function insertProducts(): void
    {
        $db = self::getContainer()->get(Connection::class);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $names = ['Chopp' => true, 'Chopp descontinuado' => false];
        for ($index = 1; \count($names) < self::PRODUCTS; ++$index) {
            $names["Pastel Queijo {$index}"] = true;
        }

        foreach (array_chunk($names, 250, preserve_keys: true) as $chunk) {
            $products = $variants = [];
            foreach ($chunk as $name => $active) {
                $productId = ProductId::generate()->toString();
                array_push($products, $productId, $this->companyId->toString(), $this->branchId->toString(), $name, 'PRODUCT', 0, (int) $active, $now, $now);
                array_push($variants, ProductVariantId::generate()->toString(), $productId, '300ml', 1200, 1, 0);
            }
            $db->executeStatement(
                'INSERT INTO products (id, company_id, branch_id, name, kind, track_stock, is_active, created_at, updated_at) VALUES '.implode(', ', array_fill(0, \count($chunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?)')),
                $products,
            );
            $db->executeStatement(
                'INSERT INTO product_variants (id, product_id, name, price_cents, is_active, sort_order) VALUES '.implode(', ', array_fill(0, \count($chunk), '(?, ?, ?, ?, ?, ?)')),
                $variants,
            );
        }
    }
}
