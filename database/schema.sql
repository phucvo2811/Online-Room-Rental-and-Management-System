CREATE DATABASE phongtro_db ENCODING 'UTF8';
\c phongtro_db;

CREATE TABLE IF NOT EXISTS users (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) UNIQUE NOT NULL,
    phone      VARCHAR(20),
    password   VARCHAR(255) NOT NULL,
    role       VARCHAR(20)  DEFAULT 'tenant' CHECK (role IN ('admin','landlord','tenant')),
    avatar     VARCHAR(255),
    status     VARCHAR(20)  DEFAULT 'active' CHECK (status IN ('active','inactive','banned')),
    created_at TIMESTAMP    DEFAULT NOW(),
    updated_at TIMESTAMP    DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS districts (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city VARCHAR(100) DEFAULT 'Cần Thơ'
);

CREATE TABLE IF NOT EXISTS wards (
    id          SERIAL PRIMARY KEY,
    district_id INT REFERENCES districts(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS streets (
    id      SERIAL PRIMARY KEY,
    ward_id INT REFERENCES wards(id) ON DELETE CASCADE,
    name    VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS rooms (
    id            SERIAL PRIMARY KEY,
    user_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title         VARCHAR(255) NOT NULL,
    description   TEXT,
    room_type     VARCHAR(30)  DEFAULT 'phong_tro',
    price         NUMERIC(12,2) NOT NULL,
    area          NUMERIC(8,1)  NOT NULL,
    address       VARCHAR(255)  NOT NULL,
    street_id     INT REFERENCES streets(id) ON DELETE SET NULL,
    ward_id       INT REFERENCES wards(id)   ON DELETE SET NULL,
    district_id   INT REFERENCES districts(id) ON DELETE SET NULL,
    max_people    INT     DEFAULT 1,
    floor         INT     DEFAULT 1,
    total_floors  INT     DEFAULT 1,
    has_wifi            BOOLEAN DEFAULT FALSE,
    has_ac              BOOLEAN DEFAULT FALSE,
    has_parking         BOOLEAN DEFAULT FALSE,
    has_kitchen         BOOLEAN DEFAULT FALSE,
    has_washing_machine BOOLEAN DEFAULT FALSE,
    has_fridge          BOOLEAN DEFAULT FALSE,
    has_private_bath    BOOLEAN DEFAULT FALSE,
    allow_pet           BOOLEAN DEFAULT FALSE,
    allow_cooking       BOOLEAN DEFAULT FALSE,
    electric_price  NUMERIC(10,2) DEFAULT 0,
    water_price     NUMERIC(10,2) DEFAULT 0,
    internet_price  NUMERIC(10,2) DEFAULT 0,
    status          VARCHAR(20) DEFAULT 'pending',
    is_available    BOOLEAN     DEFAULT TRUE,
    available_from  DATE,
    deposit_months  INT         DEFAULT 1,
    contact_name    VARCHAR(100),
    contact_phone   VARCHAR(20),
    view_count      INT         DEFAULT 0,
    created_at      TIMESTAMP   DEFAULT NOW(),
    updated_at      TIMESTAMP   DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS room_images (
    id         SERIAL PRIMARY KEY,
    room_id    INT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT     DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS reviews (
    id         SERIAL PRIMARY KEY,
    room_id    INT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating     INT CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS favorites (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    room_id    INT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (user_id, room_id)
);

CREATE TABLE IF NOT EXISTS contacts (
    id           SERIAL PRIMARY KEY,
    room_id      INT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    sender_name  VARCHAR(100) NOT NULL,
    sender_email VARCHAR(100),
    sender_phone VARCHAR(20),
    message      TEXT NOT NULL,
    is_read      BOOLEAN   DEFAULT FALSE,
    created_at   TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title      VARCHAR(255) NOT NULL,
    message    TEXT,
    type       VARCHAR(20) DEFAULT 'info',
    is_read    BOOLEAN   DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Dữ liệu mẫu
INSERT INTO users (name,email,phone,password,role) VALUES
('Admin','admin@phongtro.com','0292123456','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin'),
('Nguyễn Văn An','an@gmail.com','0901234567','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','landlord'),
('Trần Thị Bình','binh@gmail.com','0912345678','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','tenant');

INSERT INTO districts (name) VALUES ('Ninh Kiều'),('Bình Thủy'),('Cái Răng'),('Ô Môn'),('Thốt Nốt');

INSERT INTO wards (district_id,name) VALUES
(1,'An Bình'),(1,'An Cư'),(1,'An Hòa'),(1,'Hưng Lợi'),(1,'Xuân Khánh');

INSERT INTO streets (ward_id,name) VALUES
(1,'Nguyễn Văn Cừ'),(1,'30 Tháng 4'),(1,'Hùng Vương'),
(2,'Mậu Thân'),(2,'Lê Lợi'),(3,'Cách Mạng Tháng 8');

INSERT INTO rooms (user_id,title,description,room_type,price,area,address,
    street_id,ward_id,district_id,max_people,has_wifi,has_ac,has_parking,
    contact_name,contact_phone,status,is_available,available_from,deposit_months)
VALUES
(2,'Phòng trọ Nguyễn Văn Cừ full nội thất','Phòng mới xây, thoáng mát, gần chợ.',
 'phong_tro',3500000,25,'123 Nguyễn Văn Cừ, P.An Bình, Q.Ninh Kiều',
 1,1,1,2,TRUE,TRUE,TRUE,'Nguyễn Văn An','0901234567','approved',TRUE,'2025-01-01',2);