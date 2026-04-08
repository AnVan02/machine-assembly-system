-- Database schema chuẩn 7 cột cho chitiet_donhang
CREATE DATABASE IF NOT EXISTS `ho-tro-rap-may`;
USE `ho-tro-rap-may`;

CREATE TABLE IF NOT EXISTS donhang (
    id_donhang INT AUTO_INCREMENT PRIMARY KEY,
    ma_don_hang VARCHAR(50) NOT NULL,
    ten_khach_hang VARCHAR(255),
    so_luong_may INT DEFAULT 1,
    user_id INT,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chitiet_donhang (
    id_ct INT AUTO_INCREMENT PRIMARY KEY,
    id_donhang INT,
    ten_donhang VARCHAR(255),
    ten_cauhinh VARCHAR(255),
    ten_linhkien VARCHAR(255),
    loai_linhkien VARCHAR(100),
    linhkien_chon VARCHAR(255),
    so_serial VARCHAR(255),
    user_id INT,
    may_so INT,
    FOREIGN KEY (id_donhang) REFERENCES donhang(id_donhang) ON DELETE CASCADE
);

TRUNCATE TABLE chitiet_donhang;
TRUNCATE TABLE donhang;
 