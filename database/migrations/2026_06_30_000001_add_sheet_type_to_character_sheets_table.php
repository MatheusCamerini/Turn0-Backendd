<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'character_sheets_campaign_id_foreign',
            'character_sheets_sheet_model_id_foreign',
            'character_sheets_user_id_foreign',
        ] as $foreignKey) {
            $this->dropForeignIfExists('character_sheets', $foreignKey);
        }

        if ($this->indexExists('character_sheets', 'character_sheets_campaign_id_user_id_sheet_model_id_unique')) {
            Schema::table('character_sheets', function (Blueprint $table) {
                $table->dropUnique(['campaign_id', 'user_id', 'sheet_model_id']);
            });
        }

        Schema::table('character_sheets', function (Blueprint $table) {
            if (! Schema::hasColumn('character_sheets', 'sheet_type')) {
                $table->enum('sheet_type', ['player', 'npc', 'enemy'])->default('player')->after('sheet_model_id');
            }

            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('character_sheets', function (Blueprint $table) {
            if (! $this->foreignKeyExists('character_sheets', 'character_sheets_campaign_id_foreign')) {
                $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            }

            if (! $this->foreignKeyExists('character_sheets', 'character_sheets_sheet_model_id_foreign')) {
                $table->foreign('sheet_model_id')->references('id')->on('sheet_models')->cascadeOnDelete();
            }

            if (! $this->foreignKeyExists('character_sheets', 'character_sheets_user_id_foreign')) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        foreach ([
            'character_sheets_campaign_id_foreign',
            'character_sheets_sheet_model_id_foreign',
            'character_sheets_user_id_foreign',
        ] as $foreignKey) {
            $this->dropForeignIfExists('character_sheets', $foreignKey);
        }

        Schema::table('character_sheets', function (Blueprint $table) {
            if (Schema::hasColumn('character_sheets', 'sheet_type')) {
                $table->dropColumn('sheet_type');
            }

            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('character_sheets', function (Blueprint $table) {
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('sheet_model_id')->references('id')->on('sheet_models')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            if (! $this->indexExists('character_sheets', 'character_sheets_campaign_id_user_id_sheet_model_id_unique')) {
                $table->unique(['campaign_id', 'user_id', 'sheet_model_id']);
            }
        });
    }

    private function dropForeignIfExists(string $table, string $name): void
    {
        if (! $this->foreignKeyExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($name) {
            $table->dropForeign($name);
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $name, 'FOREIGN KEY']
        ) !== null;
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        ) !== null;
    }
};
