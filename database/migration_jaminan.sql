-- Jalankan file ini sekali saja untuk database sewa_iphone yang sudah ada.
ALTER TABLE tb_penyewaan
    ADD COLUMN IF NOT EXISTS jenis_jaminan VARCHAR(50) NULL AFTER id_unit,
    ADD COLUMN IF NOT EXISTS foto_jaminan VARCHAR(255) NULL AFTER jenis_jaminan;

ALTER TABLE tb_pelanggan
    ADD COLUMN IF NOT EXISTS jenis_jaminan ENUM('KTP','Kartu Pelajar','SIM','Paspor','Kartu Identitas Lainnya') NULL AFTER nomor_wa,
    ADD COLUMN IF NOT EXISTS foto_jaminan VARCHAR(255) NULL AFTER jenis_jaminan;
