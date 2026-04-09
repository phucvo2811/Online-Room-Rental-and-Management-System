-- ==============================================================
-- Migration: Cập nhật đơn vị hành chính Cần Thơ sau sáp nhập 2025
-- Nghị quyết 1668/NQ-UBTVQH15 ngày 16/6/2025
-- Có hiệu lực từ 01/07/2025
--
-- Nội dung:
--   1. Bổ sung 4 huyện còn thiếu: Phong Điền, Cờ Đỏ, Thới Lai, Vĩnh Thạnh
--   2. Xoá toàn bộ phường/xã cũ, nạp lại theo cấu trúc mới
--      (room_blocks.ward_id bị SET NULL tự động qua ON DELETE SET NULL)
--   3. Xoá toàn bộ đường phố cũ (CASCADE theo wards), nạp lại các tuyến chính
-- ==============================================================

BEGIN;

-- ============================================================
-- BƯỚC 1: Bổ sung quận/huyện còn thiếu
-- ============================================================
INSERT INTO districts (name, city)
SELECT v.name, 'Cần Thơ'
FROM (VALUES
    ('Phong Điền'),
    ('Cờ Đỏ'),
    ('Thới Lai'),
    ('Vĩnh Thạnh')
) AS v(name)
WHERE NOT EXISTS (
    SELECT 1 FROM districts d WHERE d.name = v.name
);

-- ============================================================
-- BƯỚC 2: Xoá phường/xã cũ
-- (ON DELETE CASCADE → tự xoá streets; ON DELETE SET NULL → room_blocks.ward_id = NULL)
-- ============================================================
DELETE FROM wards;

-- Reset sequence để serial bắt đầu lại từ 1
SELECT setval(pg_get_serial_sequence('wards', 'id'), 1, false);

-- ============================================================
-- BƯỚC 3: Nạp phường/xã mới theo Nghị quyết 1668/NQ-UBTVQH15
--
-- Ghi chú: Từ 01/07/2025 cấp huyện chính thức bị bãi bỏ.
-- Các tên quận/huyện được giữ nguyên trong bảng districts chỉ
-- mang nghĩa địa lý/khu vực để người dùng dễ tra cứu.
-- ============================================================

-- ─────────────── QUẬN NINH KIỀU ───────────────
-- Gộp toàn bộ 13 phường cũ của Ninh Kiều và một phần Bình Thủy
-- thành 5 phường mới theo khoản 1-5 Điều 1 NQ 1668
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Ninh Kiều'),       -- k1: Tân An + Thới Bình + Xuân Khánh
    ('Cái Khế'),         -- k2: An Hòa + Cái Khế + (phần) Bùi Hữu Nghĩa
    ('Tân An'),          -- k3: An Khánh + Hưng Lợi
    ('An Bình'),         -- k4: An Bình + Mỹ Khánh + (phần) Long Tuyền
    ('Thới An Đông')     -- k5: Trà An + Trà Nóc + Thới An Đông
) AS v(name)
WHERE d.name = 'Ninh Kiều';

-- ─────────────── QUẬN BÌNH THỦY ───────────────
-- Gộp 5 phường cũ còn lại sau khi Bùi Hữu Nghĩa, Long Tuyền
-- một phần đi qua Ninh Kiều (khoản 6-7)
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Bình Thủy'),   -- k6: An Thới + Bình Thủy + (phần) Bùi Hữu Nghĩa
    ('Long Tuyền')   -- k7: Long Hòa + (phần) Long Tuyền
) AS v(name)
WHERE d.name = 'Bình Thủy';

-- ─────────────── QUẬN CÁI RĂNG ───────────────
-- Gộp 7 phường cũ thành 2 phường mới (khoản 8-9)
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Cái Răng'),  -- k8: Lê Bình + Thường Thạnh + Ba Láng + Hưng Thạnh
    ('Hưng Phú')   -- k9: Tân Phú + Phú Thứ + Hưng Phú
) AS v(name)
WHERE d.name = 'Cái Răng';

-- ─────────────── QUẬN Ô MÔN ───────────────
-- Gộp 9 đơn vị cũ thành 3 phường mới (khoản 10-12)
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Ô Môn'),      -- k10: Châu Văn Liêm + Thới Hòa + Thới An + xã Thới Thạnh
    ('Phước Thới'), -- k11: Trường Lạc + Phước Thới
    ('Thới Long')   -- k12: Long Hưng + Tân Hưng + Thới Long
) AS v(name)
WHERE d.name = 'Ô Môn';

-- ─────────────── QUẬN THỐT NỐT ───────────────
-- Gộp các phường cũ thành 3 phường mới + giữ nguyên Tân Lộc + Mỹ Phước (khoản 13-15)
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Trung Nhứt'), -- k13: Thạnh Hòa + Trung Nhứt + xã Trung An
    ('Thuận Hưng'), -- k14: Trung Kiên + Thuận Hưng + (phần) Thốt Nốt
    ('Thốt Nốt'),   -- k15: Thuận An + Thới Thuận + (phần) Thốt Nốt
    ('Tân Lộc'),    -- không sắp xếp (giữ nguyên - phường)
    ('Mỹ Phước')    -- không sắp xếp (giữ nguyên - xã)
) AS v(name)
WHERE d.name = 'Thốt Nốt';

-- ─────────────── HUYỆN PHONG ĐIỀN ───────────────
-- 3 đơn vị: 2 xã mới + 1 xã giữ nguyên (khoản 31-32)
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Phong Điền'),  -- k31: TT Phong Điền + Tân Thới + Giai Xuân
    ('Nhơn Ái'),     -- k32: Nhơn Nghĩa + Nhơn Ái
    ('Trường Long')  -- không sắp xếp (giữ nguyên - xã)
) AS v(name)
WHERE d.name = 'Phong Điền';

-- ─────────────── HUYỆN CỜ ĐỎ ───────────────
-- 3 xã mới + 2 xã giữ nguyên (khoản 37-39)
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Cờ Đỏ'),      -- k37: TT Cờ Đỏ + Thới Đông + Thới Xuân
    ('Đông Hiệp'),  -- k38: Đông Thắng + Xuân Thắng + Đông Hiệp
    ('Trung Hưng'), -- k39: Trung Thạnh + Trung Hưng
    ('Thới Hưng'),  -- không sắp xếp (giữ nguyên - xã)
    ('Phong Nẫm')   -- không sắp xếp (giữ nguyên - xã)
) AS v(name)
WHERE d.name = 'Cờ Đỏ';

-- ─────────────── HUYỆN THỚI LAI ───────────────
-- 4 xã mới + 1 xã giữ nguyên (khoản 33-36)
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Thới Lai'),    -- k33: TT Thới Lai + Thới Tân + Trường Thắng
    ('Đông Thuận'),  -- k34: Đông Bình + Đông Thuận
    ('Trường Xuân'), -- k35: Trường Xuân A + Trường Xuân B + Trường Xuân
    ('Trường Thành'),-- k36: Tân Thạnh + Định Môn + Trường Thành
    ('Thạnh Phú')    -- không sắp xếp (giữ nguyên - xã)
) AS v(name)
WHERE d.name = 'Thới Lai';

-- ─────────────── HUYỆN VĨNH THẠNH ───────────────
-- 4 xã mới (khoản 40-43)
INSERT INTO wards (district_id, name)
SELECT d.id, v.name
FROM districts d
CROSS JOIN (VALUES
    ('Vĩnh Thạnh'),  -- k40: TT Vĩnh Thạnh + Thạnh Lộc + Thạnh Mỹ
    ('Vĩnh Trinh'),  -- k41: Vĩnh Bình + Vĩnh Trinh
    ('Thạnh An'),    -- k42: TT Thạnh An + Thạnh Lợi + Thạnh Thắng
    ('Thạnh Quới')   -- k43: Thạnh Tiến + Thạnh An + Thạnh Quới
) AS v(name)
WHERE d.name = 'Vĩnh Thạnh';

-- ============================================================
-- BƯỚC 4: Nạp lại đường phố chính khu vực nội ô
-- ============================================================

-- Đường phố thuộc P. Ninh Kiều (trung tâm TP)
INSERT INTO streets (ward_id, name)
SELECT w.id, s.name
FROM wards w
JOIN districts d ON w.district_id = d.id
CROSS JOIN (VALUES
    ('Nguyễn Văn Cừ'),
    ('30 Tháng 4'),
    ('Hùng Vương'),
    ('Trần Hưng Đạo'),
    ('Ngô Quyền'),
    ('Hai Bà Trưng'),
    ('Lý Tự Trọng'),
    ('Phan Đình Phùng')
) AS s(name)
WHERE d.name = 'Ninh Kiều' AND w.name = 'Ninh Kiều';

-- Đường phố thuộc P. Cái Khế
INSERT INTO streets (ward_id, name)
SELECT w.id, s.name
FROM wards w
JOIN districts d ON w.district_id = d.id
CROSS JOIN (VALUES
    ('Mậu Thân'),
    ('3 Tháng 2'),
    ('Cách Mạng Tháng 8'),
    ('Lê Lợi')
) AS s(name)
WHERE d.name = 'Ninh Kiều' AND w.name = 'Cái Khế';

-- Đường phố thuộc P. Tân An
INSERT INTO streets (ward_id, name)
SELECT w.id, s.name
FROM wards w
JOIN districts d ON w.district_id = d.id
CROSS JOIN (VALUES
    ('Nguyễn Trãi'),
    ('Quang Trung'),
    ('Lê Thánh Tôn')
) AS s(name)
WHERE d.name = 'Ninh Kiều' AND w.name = 'Tân An';

-- Đường phố thuộc P. An Bình
INSERT INTO streets (ward_id, name)
SELECT w.id, s.name
FROM wards w
JOIN districts d ON w.district_id = d.id
CROSS JOIN (VALUES
    ('Hoàng Quốc Việt'),
    ('Nguyễn Văn Linh'),
    ('Võ Văn Kiệt')
) AS s(name)
WHERE d.name = 'Ninh Kiều' AND w.name = 'An Bình';

-- Đường phố thuộc P. Bình Thủy
INSERT INTO streets (ward_id, name)
SELECT w.id, s.name
FROM wards w
JOIN districts d ON w.district_id = d.id
CROSS JOIN (VALUES
    ('Lê Hồng Phong'),
    ('Bình Thủy'),
    ('Cách Mạng Tháng 8')
) AS s(name)
WHERE d.name = 'Bình Thủy' AND w.name = 'Bình Thủy';

-- Đường phố thuộc P. Cái Răng
INSERT INTO streets (ward_id, name)
SELECT w.id, s.name
FROM wards w
JOIN districts d ON w.district_id = d.id
CROSS JOIN (VALUES
    ('Trần Chiên'),
    ('Nguyễn Văn Cừ nối dài'),
    ('Võ Nguyên Giáp')
) AS s(name)
WHERE d.name = 'Cái Răng' AND w.name = 'Cái Răng';

-- ============================================================
-- XÁC NHẬN KẾT QUẢ
-- ============================================================
DO $$
DECLARE
    d_count INT;
    w_count INT;
    s_count INT;
BEGIN
    SELECT COUNT(*) INTO d_count FROM districts;
    SELECT COUNT(*) INTO w_count FROM wards;
    SELECT COUNT(*) INTO s_count FROM streets;
    RAISE NOTICE 'Hoàn thành cập nhật vị trí:';
    RAISE NOTICE '  Quận/huyện: % đơn vị', d_count;
    RAISE NOTICE '  Phường/xã:  % đơn vị', w_count;
    RAISE NOTICE '  Đường phố:  % tuyến', s_count;
END $$;

COMMIT;
