-- 1. Tambah kolom penyimpanan ke tb_unit_iphone
ALTER TABLE tb_unit_iphone 
ADD COLUMN penyimpanan VARCHAR(20) NOT NULL DEFAULT '128GB' AFTER id_model;

-- 2. Migrasikan data penyimpanan yang sudah ada dari model ke masing-masing unit
UPDATE tb_unit_iphone u 
JOIN tb_iphone_model m ON m.id_model = u.id_model 
SET u.penyimpanan = CASE 
    WHEN m.penyimpanan = '256' THEN '256GB' 
    WHEN m.penyimpanan = '128' THEN '128GB'
    WHEN m.penyimpanan = '512' THEN '512GB'
    WHEN m.penyimpanan = '64' THEN '64GB'
    ELSE m.penyimpanan 
END;

-- 3. Hapus kolom penyimpanan dari tb_iphone_model
ALTER TABLE tb_iphone_model 
DROP COLUMN penyimpanan;
