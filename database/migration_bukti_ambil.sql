-- Migration untuk menambahkan bukti pengambilan unit
ALTER TABLE tb_penyewaan 
    ADD COLUMN IF NOT EXISTS foto_bukti_ambil VARCHAR(255) NULL AFTER foto_jaminan;
