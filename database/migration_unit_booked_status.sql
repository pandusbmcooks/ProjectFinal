-- Tambahkan 'booked' ke ENUM status tb_unit_iphone
ALTER TABLE tb_unit_iphone 
MODIFY COLUMN status ENUM('ready','booked','disewa','maintenance','hilang') NOT NULL DEFAULT 'ready';
