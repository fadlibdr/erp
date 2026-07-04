<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Database-level backstop for the double-entry invariant.
 *
 * The application already refuses to build an unbalanced JournalDraft, but the
 * ledger is too important to trust a single guard. This deferred constraint
 * trigger re-checks, at COMMIT time, that every journal's lines sum to zero
 * (Σdebit − Σcredit = 0). Because it is DEFERRABLE INITIALLY DEFERRED, a journal
 * and its lines can be inserted in any order within the transaction and are only
 * validated when the transaction commits. Any code path that ever tries to write
 * an unbalanced journal — a bug, a bad migration, a manual SQL fix — is rejected
 * by Postgres itself.
 *
 * Postgres-only. On SQLite (fast unit tests) this migration is a no-op and the
 * application-level guard stands alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fin_assert_journal_balanced()
            RETURNS trigger AS $$
            DECLARE
                imbalance bigint;
                target_journal uuid;
            BEGIN
                target_journal := COALESCE(NEW.journal_id, OLD.journal_id);

                SELECT COALESCE(SUM(debit_minor), 0) - COALESCE(SUM(credit_minor), 0)
                INTO imbalance
                FROM fin_journal_lines
                WHERE journal_id = target_journal;

                IF imbalance <> 0 THEN
                    RAISE EXCEPTION 'Journal % is unbalanced by % minor units', target_journal, imbalance;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER fin_journal_balance_check
            AFTER INSERT OR UPDATE OR DELETE ON fin_journal_lines
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION fin_assert_journal_balanced();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS fin_journal_balance_check ON fin_journal_lines;');
        DB::unprepared('DROP FUNCTION IF EXISTS fin_assert_journal_balanced();');
    }
};
