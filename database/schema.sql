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

CREATE TABLE IF NOT EXISTS banners (
    id         SERIAL PRIMARY KEY,
    image_url  VARCHAR(255) NOT NULL,
    link       VARCHAR(255),
    status     VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active','inactive')),
    "order"   INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id            SERIAL PRIMARY KEY,
    user_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plan          VARCHAR(50) NOT NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','expired','cancelled')),
    duration_days INT NOT NULL,
    amount        NUMERIC(12,2) DEFAULT 0,
    starts_at     TIMESTAMP NOT NULL DEFAULT NOW(),
    ends_at       TIMESTAMP NOT NULL,
    created_at    TIMESTAMP DEFAULT NOW(),
    updated_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS landlord_profiles (
    id             SERIAL PRIMARY KEY,
    user_id        INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    purchase_cost  NUMERIC(12,2) DEFAULT 0,
    rent_per_room  NUMERIC(12,2) DEFAULT 0,
    rooms_count    INT DEFAULT 0,
    monthly_expenses NUMERIC(12,2) DEFAULT 0,
    created_at     TIMESTAMP DEFAULT NOW(),
    updated_at     TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS financial_records (
    id                SERIAL PRIMARY KEY,
    user_id           INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    record_date       DATE NOT NULL DEFAULT CURRENT_DATE,
    purchase_cost     NUMERIC(12,2) DEFAULT 0,
    rent_per_room     NUMERIC(12,2) DEFAULT 0,
    rooms_count       INT DEFAULT 0,
    monthly_expenses  NUMERIC(12,2) DEFAULT 0,
    monthly_revenue   NUMERIC(12,2) DEFAULT 0,
    monthly_profit    NUMERIC(12,2) DEFAULT 0,
    payback_months    NUMERIC(12,2),
    created_at        TIMESTAMP DEFAULT NOW(),
    updated_at        TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS room_edit_history (
    id          SERIAL PRIMARY KEY,
    room_id     INT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE SET NULL,
    old_data    JSONB NOT NULL,
    new_data    JSONB NOT NULL,
    reason      VARCHAR(255),
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS reports (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    room_id     INT NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
    report_type VARCHAR(50) NOT NULL,
    message     TEXT,
    status      VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open','processing','resolved')),
    created_at  TIMESTAMP DEFAULT NOW(),
    resolved_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id          SERIAL PRIMARY KEY,
    user_id     INT NULL REFERENCES users(id) ON DELETE SET NULL,
    action      VARCHAR(100) NOT NULL,
    target_type VARCHAR(100),
    target_id   INT,
    metadata    JSONB,
    created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS room_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    default_price NUMERIC(12,2) DEFAULT 0,
    default_area NUMERIC(8,1) DEFAULT 0,
    amenities TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS room_blocks (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(30) NOT NULL DEFAULT 'boarding_house' CHECK (type IN ('boarding_house','dormitory','mini_house','full_house','homestay')),
    price NUMERIC(12,2),
    price_min NUMERIC(12,2),
    price_max NUMERIC(12,2),
    area NUMERIC(8,1),
    district_id     INT REFERENCES districts(id) ON DELETE SET NULL,
    ward_id         INT REFERENCES wards(id)     ON DELETE SET NULL,
    street_id       INT REFERENCES streets(id)   ON DELETE SET NULL,
    has_wifi        BOOLEAN       DEFAULT FALSE,
    has_ac          BOOLEAN       DEFAULT FALSE,
    has_parking     BOOLEAN       DEFAULT FALSE,
    allow_pet       BOOLEAN       DEFAULT FALSE,
    allow_cooking   BOOLEAN       DEFAULT FALSE,
    electric_price  NUMERIC(10,2) DEFAULT 0,
    water_price     NUMERIC(10,2) DEFAULT 0,
    internet_price  NUMERIC(10,2) DEFAULT 0,
    deposit_months  INT           DEFAULT 1,
    floor           INT           DEFAULT 1,
    max_people      INT           DEFAULT 1,
    num_bedrooms    INT,
    num_bathrooms   INT,
    contact_phone   VARCHAR(20),
    latitude        DOUBLE PRECISION,
    longitude       DOUBLE PRECISION,
    map_address     VARCHAR(500),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS posts (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL CHECK (type IN ('room','block')),
    room_id INT REFERENCES rooms(id) ON DELETE CASCADE,
    block_id INT REFERENCES room_blocks(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price_low NUMERIC(12,2),
    price_high NUMERIC(12,2),
    image_url VARCHAR(255),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active','inactive')),
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS block_images (
    id SERIAL PRIMARY KEY,
    block_id INT NOT NULL REFERENCES room_blocks(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

ALTER TABLE rooms
    ADD COLUMN IF NOT EXISTS property_id INT REFERENCES room_blocks(id) ON DELETE CASCADE,
    ADD COLUMN IF NOT EXISTS room_number INT,
    ADD COLUMN IF NOT EXISTS moderation_status VARCHAR(20) DEFAULT 'pending' CHECK (moderation_status IN ('pending','approved','rejected','hidden')),
    ADD COLUMN IF NOT EXISTS is_spam BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS block_id INT REFERENCES room_blocks(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS room_type_id INT REFERENCES room_types(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS occupancy_status VARCHAR(20) DEFAULT 'available' CHECK (occupancy_status IN ('available','rented','maintenance'));

CREATE UNIQUE INDEX IF NOT EXISTS idx_rooms_property_room_number ON rooms (property_id, room_number);

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_trusted_landlord BOOLEAN DEFAULT FALSE;

ALTER TABLE subscriptions
    ADD COLUMN IF NOT EXISTS price NUMERIC(12,2) DEFAULT 0;


-- Dữ liệu mẫu
INSERT INTO users (name,email,phone,password,role) VALUES
('Admin','admin@phongtro.com','0292123456','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin'),
('Nguyễn Văn An','an@gmail.com','0901234567','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','landlord'),
('Trần Thị Bình','binh@gmail.com','0912345678','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','tenant')
ON CONFLICT (email) DO NOTHING;

-- Quận/huyện Cần Thơ (cập nhật theo cấu trúc sau sáp nhập 01/07/2025)
-- Cấp huyện đã bãi bỏ, giữ tên quận/huyện cũ làm khu vực địa lý tham chiếu
INSERT INTO districts (name, city) VALUES
    ('Ninh Kiều',   'Cần Thơ'),
    ('Bình Thủy',   'Cần Thơ'),
    ('Cái Răng',    'Cần Thơ'),
    ('Ô Môn',       'Cần Thơ'),
    ('Thốt Nốt',    'Cần Thơ'),
    ('Phong Điền',  'Cần Thơ'),
    ('Cờ Đỏ',       'Cần Thơ'),
    ('Thới Lai',    'Cần Thơ'),
    ('Vĩnh Thạnh',  'Cần Thơ')
ON CONFLICT DO NOTHING;

-- Phường/xã mới theo Nghị quyết 1668/NQ-UBTVQH15 ngày 16/6/2025 (hiệu lực 01/07/2025)
INSERT INTO wards (district_id, name) VALUES
    -- Ninh Kiều (5 phường, gộp từ 13 phường cũ của Q. Ninh Kiều + phần Q. Bình Thủy)
    (1, 'Ninh Kiều'),       -- = Tân An + Thới Bình + Xuân Khánh
    (1, 'Cái Khế'),         -- = An Hòa + Cái Khế + (phần) Bùi Hữu Nghĩa
    (1, 'Tân An'),          -- = An Khánh + Hưng Lợi
    (1, 'An Bình'),         -- = An Bình + Mỹ Khánh + (phần) Long Tuyền
    (1, 'Thới An Đông'),    -- = Trà An + Trà Nóc + Thới An Đông
    -- Bình Thủy (2 phường)
    (2, 'Bình Thủy'),       -- = An Thới + Bình Thủy + (phần) Bùi Hữu Nghĩa
    (2, 'Long Tuyền'),      -- = Long Hòa + (phần) Long Tuyền
    -- Cái Răng (2 phường)
    (3, 'Cái Răng'),        -- = Lê Bình + Thường Thạnh + Ba Láng + Hưng Thạnh
    (3, 'Hưng Phú'),        -- = Tân Phú + Phú Thứ + Hưng Phú
    -- Ô Môn (3 phường)
    (4, 'Ô Môn'),           -- = Châu Văn Liêm + Thới Hòa + Thới An + xã Thới Thạnh
    (4, 'Phước Thới'),      -- = Trường Lạc + Phước Thới
    (4, 'Thới Long'),       -- = Long Hưng + Tân Hưng + Thới Long
    -- Thốt Nốt (3 phường mới + 2 giữ nguyên)
    (5, 'Trung Nhứt'),      -- = Thạnh Hòa + Trung Nhứt + xã Trung An
    (5, 'Thuận Hưng'),      -- = Trung Kiên + Thuận Hưng + (phần) Thốt Nốt
    (5, 'Thốt Nốt'),        -- = Thuận An + Thới Thuận + (phần) Thốt Nốt
    (5, 'Tân Lộc'),         -- giữ nguyên (phường)
    (5, 'Mỹ Phước'),        -- giữ nguyên (xã)
    -- Phong Điền (2 xã mới + 1 giữ nguyên)
    (6, 'Phong Điền'),      -- = TT Phong Điền + Tân Thới + Giai Xuân
    (6, 'Nhơn Ái'),         -- = Nhơn Nghĩa + Nhơn Ái
    (6, 'Trường Long'),     -- giữ nguyên (xã)
    -- Cờ Đỏ (3 xã mới + 2 giữ nguyên)
    (7, 'Cờ Đỏ'),           -- = TT Cờ Đỏ + Thới Đông + Thới Xuân
    (7, 'Đông Hiệp'),       -- = Đông Thắng + Xuân Thắng + Đông Hiệp
    (7, 'Trung Hưng'),      -- = Trung Thạnh + Trung Hưng
    (7, 'Thới Hưng'),       -- giữ nguyên (xã)
    (7, 'Phong Nẫm'),       -- giữ nguyên (xã)
    -- Thới Lai (4 xã mới + 1 giữ nguyên)
    (8, 'Thới Lai'),        -- = TT Thới Lai + Thới Tân + Trường Thắng
    (8, 'Đông Thuận'),      -- = Đông Bình + Đông Thuận
    (8, 'Trường Xuân'),     -- = Trường Xuân A + Trường Xuân B + Trường Xuân
    (8, 'Trường Thành'),    -- = Tân Thạnh + Định Môn + Trường Thành
    (8, 'Thạnh Phú'),       -- giữ nguyên (xã)
    -- Vĩnh Thạnh (4 xã mới)
    (9, 'Vĩnh Thạnh'),      -- = TT Vĩnh Thạnh + Thạnh Lộc + Thạnh Mỹ
    (9, 'Vĩnh Trinh'),      -- = Vĩnh Bình + Vĩnh Trinh
    (9, 'Thạnh An'),        -- = TT Thạnh An + Thạnh Lợi + Thạnh Thắng
    (9, 'Thạnh Quới')       -- = Thạnh Tiến + Thạnh An + Thạnh Quới
ON CONFLICT DO NOTHING;

-- Đường phố chính khu vực nội ô Ninh Kiều
INSERT INTO streets (ward_id, name) VALUES
    (1,'Nguyễn Văn Cừ'),(1,'30 Tháng 4'),(1,'Hùng Vương'),
    (1,'Trần Hưng Đạo'),(1,'Ngô Quyền'),(1,'Hai Bà Trưng'),
    (1,'Lý Tự Trọng'),(1,'Phan Đình Phùng'),
    (2,'Mậu Thân'),(2,'3 Tháng 2'),(2,'Cách Mạng Tháng 8'),(2,'Lê Lợi'),
    (3,'Nguyễn Trãi'),(3,'Quang Trung'),(3,'Lê Thánh Tôn'),
    (4,'Hoàng Quốc Việt'),(4,'Nguyễn Văn Linh'),(4,'Võ Văn Kiệt'),
    (6,'Lê Hồng Phong'),(6,'Cách Mạng Tháng 8'),
    (8,'Nguyễn Văn Cừ nối dài'),(8,'Võ Nguyên Giáp')
ON CONFLICT DO NOTHING;

INSERT INTO room_types (name,slug,default_price,default_area,amenities) VALUES
('Phòng trọ','phong_tro',3000000,20,'wifi,điều hòa'),
('Chung cư mini','chung_cu_mini',5000000,30,'wifi,điều hòa,thang máy'),
('Nhà nguyên căn','nha_nguyen_can',12000000,80,'wifi,điều hòa,bếp cá nhân')
ON CONFLICT (slug) DO NOTHING;

INSERT INTO room_blocks (user_id,name,address,description,type)
SELECT 2,'Nhà trọ A','123 Nguyễn Văn Cừ, P.An Bình','Nhà trọ gần chợ, phù hợp sinh viên','boarding_house'
WHERE NOT EXISTS (SELECT 1 FROM room_blocks WHERE name='Nhà trọ A' AND user_id=2);

INSERT INTO room_blocks (user_id,name,address,description,type)
SELECT 2,'Nhà trọ B','67 Lê Lợi, P.An Cư','Nhà trọ mới, gần trung tâm','boarding_house'
WHERE NOT EXISTS (SELECT 1 FROM room_blocks WHERE name='Nhà trọ B' AND user_id=2);

INSERT INTO rooms (user_id,title,description,room_type,room_type_id,price,area,address,
    street_id,ward_id,district_id,max_people,has_wifi,has_ac,has_parking,
    contact_name,contact_phone,status,is_available,available_from,deposit_months,block_id,occupancy_status)
SELECT 2,'Phòng A01','Phòng tầng trệt','phong_tro',1,3500000,25,'123 Nguyễn Văn Cừ, P.An Bình, Q.Ninh Kiều',
 1,1,1,2,TRUE,TRUE,TRUE,'Nguyễn Văn An','0901234567','approved',TRUE,'2025-01-01',2,
 (SELECT id FROM room_blocks WHERE name='Nhà trọ A' AND user_id=2 LIMIT 1),'available'
WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE title='Phòng A01' AND user_id=2);

INSERT INTO rooms (user_id,title,description,room_type,room_type_id,price,area,address,
    street_id,ward_id,district_id,max_people,has_wifi,has_ac,has_parking,
    contact_name,contact_phone,status,is_available,available_from,deposit_months,block_id,occupancy_status)
SELECT 2,'Phòng B01','Phòng tầng 1','phong_tro',1,3600000,26,'67 Lê Lợi, P.An Cư, Q.Ninh Kiều',
 5,2,1,2,TRUE,TRUE,TRUE,'Nguyễn Văn An','0901234567','approved',TRUE,'2025-01-01',2,
 (SELECT id FROM room_blocks WHERE name='Nhà trọ B' AND user_id=2 LIMIT 1),'rented'
WHERE NOT EXISTS (SELECT 1 FROM rooms WHERE title='Phòng B01' AND user_id=2);

INSERT INTO posts (user_id, type, block_id, title, description, price_low, price_high, image_url, status)
SELECT 2,'block',(SELECT id FROM room_blocks WHERE name='Nhà trọ A' AND user_id=2 LIMIT 1),
    'Nhà trọ A cho thuê','Nhà trọ A đầy đủ tiện nghi, nhiều phòng trống',3500000,4500000,'/public/uploads/sample_block.png','active'
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE title='Nhà trọ A cho thuê' AND user_id=2);

INSERT INTO posts (user_id, type, block_id, title, description, price_low, price_high, image_url, status)
SELECT 2,'room',NULL,'Phòng B01 - giá tốt','Phòng B01, cạnh cầu, đủ nội thất',3600000,3600000,'/public/uploads/sample_room.png','active'
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE title='Phòng B01 - giá tốt' AND user_id=2);