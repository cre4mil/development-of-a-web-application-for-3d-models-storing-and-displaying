<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class PdoPayoutRepository implements PayoutStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function availableBalance(int $creatorId): ?float
    {
        $statement = $this->pdo->prepare('SELECT available_balance FROM creator_wallets WHERE creator_id = ?');
        $statement->execute([$creatorId]);
        $balance = $statement->fetchColumn();

        return $balance === false ? null : (float) $balance;
    }

    public function createRequest(int $creatorId, float $amount, int $adminId): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO payout_requests (creator_id, amount, status, admin_id) VALUES (?, ?, 'pending', ?)"
        );
        $statement->execute([$creatorId, $amount, $adminId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findRequest(int $payoutId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM payout_requests WHERE id = ?');
        $statement->execute([$payoutId]);
        $payout = $statement->fetch();

        return $payout === false ? null : $payout;
    }

    public function markTransferred(int $payoutId, int $adminId, string $slipPath): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE payout_requests SET status = 'transferred', admin_transfer_slip = ?, transferred_at = " . $this->nowExpression() . ', admin_id = ? WHERE id = ?'
        );
        $statement->execute([$slipPath, $adminId, $payoutId]);
    }

    public function markRejected(int $payoutId, string $note): void
    {
        $statement = $this->pdo->prepare("UPDATE payout_requests SET status = 'rejected', admin_note = ? WHERE id = ?");
        $statement->execute([$note, $payoutId]);
    }

    public function moveToPending(int $creatorId, float $amount): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE creator_wallets SET available_balance = available_balance - ?, pending_payout = pending_payout + ? WHERE creator_id = ?'
        );
        $statement->execute([$amount, $amount, $creatorId]);
    }

    public function settlePending(int $creatorId, float $amount): void
    {
        $statement = $this->pdo->prepare('UPDATE creator_wallets SET pending_payout = pending_payout - ? WHERE creator_id = ?');
        $statement->execute([$amount, $creatorId]);
    }

    public function restorePending(int $creatorId, float $amount): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE creator_wallets SET available_balance = available_balance + ?, pending_payout = pending_payout - ? WHERE creator_id = ?'
        );
        $statement->execute([$amount, $amount, $creatorId]);
    }

    public function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function nowExpression(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
