<?php

namespace Tests\Unit;

use App\Support\RetryingMySqlConnector;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/** Batas koneksi MySQL hosting: coba ulang hanya untuk penolakan karena batas, bukan error lain. */
class RetryingMySqlConnectorTest extends TestCase
{
    private function connector(array $failures): RetryingMySqlConnector
    {
        return new class($failures) extends RetryingMySqlConnector
        {
            public int $attempts = 0;

            public array $pauses = [];

            public function __construct(private array $failures) {}

            public function open(): PDO
            {
                return $this->createPdoConnection('mysql:host=localhost', 'u', 'p', []);
            }

            protected function connectOnce($dsn, $username, $password, $options): PDO
            {
                $this->attempts++;
                if ($message = array_shift($this->failures)) {
                    throw new PDOException($message);
                }

                return new PDO('sqlite::memory:');
            }

            protected function pause(int $milliseconds): void
            {
                $this->pauses[] = $milliseconds;
            }
        };
    }

    public function test_retries_when_host_connection_limit_is_reached(): void
    {
        $limit = 'SQLSTATE[HY000] [2002] Operation not permitted';
        $connector = $this->connector([$limit, $limit, $limit]);

        $this->assertInstanceOf(PDO::class, $connector->open());
        $this->assertSame(4, $connector->attempts);
        $this->assertCount(3, $connector->pauses);
    }

    public function test_other_errors_fail_immediately(): void
    {
        $connector = $this->connector(["SQLSTATE[HY000] [1045] Access denied for user 'x'@'localhost'"]);

        try {
            $connector->open();
            $this->fail('Seharusnya melempar exception.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('[1045]', $e->getMessage());
        }
        $this->assertSame(1, $connector->attempts);
        $this->assertSame([], $connector->pauses);
    }

    public function test_gives_up_after_all_retries(): void
    {
        $limit = 'SQLSTATE[HY000] [2002] Operation not permitted';
        $connector = $this->connector(array_fill(0, 20, $limit));

        $this->expectException(PDOException::class);
        try {
            $connector->open();
        } finally {
            $this->assertSame(count(RetryingMySqlConnector::DELAYS_MS) + 1, $connector->attempts);
            // Total jeda dasar ±2,85 detik (+ jitter maksimal sama besar).
            $this->assertLessThanOrEqual(2 * array_sum(RetryingMySqlConnector::DELAYS_MS), array_sum($connector->pauses));
        }
    }
}
