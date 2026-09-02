<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->renameColumn('start_at', 'mulai_pada');
            $table->renameColumn('end_at', 'berakhir_pada');
            $table->renameColumn('target_user_ids', 'id_pengguna_target');
            $table->renameColumn('amount_paid', 'jumlah_dibayar');
            $table->renameColumn('payment_status', 'status_pembayaran');
            $table->renameColumn('payment_method', 'metode_pembayaran');
            $table->renameColumn('manual_proof_path', 'jalur_bukti_manual');
            $table->renameColumn('manual_review_status', 'status_peninjauan_manual');
            $table->renameColumn('ocr_note', 'catatan_ocr');
            $table->renameColumn('ad_type', 'jenis_iklan');
            $table->renameColumn('ad_media_url', 'url_media_iklan');
            $table->renameColumn('ad_title', 'judul_iklan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->renameColumn('mulai_pada', 'start_at');
            $table->renameColumn('berakhir_pada', 'end_at');
            $table->renameColumn('id_pengguna_target', 'target_user_ids');
            $table->renameColumn('jumlah_dibayar', 'amount_paid');
            $table->renameColumn('status_pembayaran', 'payment_status');
            $table->renameColumn('metode_pembayaran', 'payment_method');
            $table->renameColumn('jalur_bukti_manual', 'manual_proof_path');
            $table->renameColumn('status_peninjauan_manual', 'manual_review_status');
            $table->renameColumn('catatan_ocr', 'ocr_note');
            $table->renameColumn('jenis_iklan', 'ad_type');
            $table->renameColumn('url_media_iklan', 'ad_media_url');
            $table->renameColumn('judul_iklan', 'ad_title');
        });
    }
};
