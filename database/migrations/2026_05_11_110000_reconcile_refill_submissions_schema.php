<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('refill_submissions')) {
            return;
        }

        if (Schema::hasColumn('refill_submissions', 'cached_response')
            && ! Schema::hasColumn('refill_submissions', 'response_payload')) {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->renameColumn('cached_response', 'response_payload');
            });
        }

        if (Schema::hasColumn('refill_submissions', 'last_error')
            && ! Schema::hasColumn('refill_submissions', 'error_message')) {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->renameColumn('last_error', 'error_message');
            });
        }

        Schema::table('refill_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('refill_submissions', 'device_id')) {
                $table->unsignedBigInteger('device_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('refill_submissions', 'device_order_id')) {
                $table->unsignedBigInteger('device_order_id')->nullable()->after('device_id');
            }

            if (! Schema::hasColumn('refill_submissions', 'client_submission_id')) {
                $table->uuid('client_submission_id')->nullable()->after('device_order_id');
            }

            if (! Schema::hasColumn('refill_submissions', 'status')) {
                $table->string('status', 30)->default('NEW')->after('client_submission_id');
            }

            if (! Schema::hasColumn('refill_submissions', 'print_event_id')) {
                $table->unsignedBigInteger('print_event_id')->nullable()->after('status');
            }

            if (! Schema::hasColumn('refill_submissions', 'pos_ordered_menu_ids')) {
                $table->json('pos_ordered_menu_ids')->nullable()->after('print_event_id');
            }

            if (! Schema::hasColumn('refill_submissions', 'response_payload')) {
                $table->json('response_payload')->nullable()->after('pos_ordered_menu_ids');
            }

            if (! Schema::hasColumn('refill_submissions', 'response_status')) {
                $table->unsignedSmallInteger('response_status')->nullable()->after('response_payload');
            }

            if (! Schema::hasColumn('refill_submissions', 'error_message')) {
                $table->text('error_message')->nullable()->after('response_status');
            }

            if (! Schema::hasColumn('refill_submissions', 'processing_started_at')) {
                $table->timestamp('processing_started_at')->nullable()->after('error_message');
            }

            if (! Schema::hasColumn('refill_submissions', 'processing_lock_id')) {
                $table->string('processing_lock_id', 64)->nullable()->after('processing_started_at');
            }

            if (! Schema::hasColumn('refill_submissions', 'pos_created_at')) {
                $table->timestamp('pos_created_at')->nullable()->after('response_status');
            }

            if (! Schema::hasColumn('refill_submissions', 'mirrored_at')) {
                $table->timestamp('mirrored_at')->nullable()->after('pos_created_at');
            }

            if (! Schema::hasColumn('refill_submissions', 'print_event_created_at')) {
                $table->timestamp('print_event_created_at')->nullable()->after('mirrored_at');
            }

            if (! Schema::hasColumn('refill_submissions', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('print_event_created_at');
            }

            if (! Schema::hasColumn('refill_submissions', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('completed_at');
            }
        });

        try {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->index('client_submission_id');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->index('processing_lock_id');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->index(['device_order_id', 'status']);
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->index(['status', 'created_at']);
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->unique(
                    ['device_id', 'device_order_id', 'client_submission_id'],
                    'unique_refill_submission'
                );
            });
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('refill_submissions')) {
            return;
        }

        $columnsToDrop = [];

        foreach ([
            'response_status',
            'print_event_id',
        ] as $column) {
            if (Schema::hasColumn('refill_submissions', $column)) {
                $columnsToDrop[] = $column;
            }
        }

        if ($columnsToDrop !== []) {
            Schema::table('refill_submissions', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        if (Schema::hasColumn('refill_submissions', 'response_payload')
            && ! Schema::hasColumn('refill_submissions', 'cached_response')) {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->renameColumn('response_payload', 'cached_response');
            });
        }

        if (Schema::hasColumn('refill_submissions', 'error_message')
            && ! Schema::hasColumn('refill_submissions', 'last_error')) {
            Schema::table('refill_submissions', function (Blueprint $table) {
                $table->renameColumn('error_message', 'last_error');
            });
        }
    }
};
