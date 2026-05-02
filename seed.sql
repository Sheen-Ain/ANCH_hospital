-- =============================================
-- TokenMed — Seed Data
-- Run AFTER schema.sql
-- =============================================

USE `token_med`;

-- =============================================
-- USERS  (passwords hashed with bcrypt)
-- admin@hospital.com       → admin123
-- receptionist1@hospital.com → rec123
-- receptionist2@hospital.com → rec123
-- =============================================

INSERT INTO `users` (`role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `gender`, `is_active`) VALUES
(1, 'Sana',  'Mirza',   'admin@hospital.com',          '0300-1234567', '$2y$10$1QfOQ8IBB3N/MnWvUQzJ/uHWu/P1tyUGzcP9qCm4pxsf6oR57CB.W', 'female', 1),
(2, 'Ayesha','Siddiqui','receptionist1@hospital.com',  '0301-2345678', '$2y$10$gy2hyZFOt1xD6kJyAqBweOqv9Dgnzc8yLJCC/mbpMjK5zxPr8TXWy', 'female', 1),
(2, 'Bilal', 'Ahmed',   'receptionist2@hospital.com',  '0302-3456789', '$2y$10$QZ28.jTk1sbFRezNx.l1.OdUlYTH472g5BEwpxuuab.FcBAbGpsO.', 'male',   1);

-- =============================================
-- DOCTORS
-- =============================================

INSERT INTO `doctors` (`name`, `email`, `phone`, `gender`, `designation`, `specialization`, `fee`, `is_active`) VALUES
('Dr. Hassan Raza',     'hassan.raza@hospital.com',     '0311-1111111', 'male',   'MBBS, FCPS',       'Cardiology',          800.00, 1),
('Dr. Farah Noor',      'farah.noor@hospital.com',      '0312-2222222', 'female', 'MBBS, FCPS Paeds', 'Pediatrics',          600.00, 1),
('Dr. Imran Qureshi',   'imran.qureshi@hospital.com',   '0313-3333333', 'male',   'MBBS, MS Ortho',   'Orthopedics',         900.00, 1),
('Dr. Nadia Khalid',    'nadia.khalid@hospital.com',    '0314-4444444', 'female', 'MBBS, FCPS',       'Gynecology',          700.00, 1),
('Dr. Tariq Mehmood',   'tariq.mehmood@hospital.com',   '0315-5555555', 'male',   'MBBS, MRCP',       'Internal Medicine',   500.00, 1),
('Dr. Zara Baig',       'zara.baig@hospital.com',       '0316-6666666', 'female', 'MBBS, MRCOphth',   'Ophthalmology',       600.00, 0);

-- =============================================
-- ROOMS
-- =============================================

INSERT INTO `rooms` (`room_number`, `room_type`, `daily_fee`, `is_active`) VALUES
('G-101', 'general', 1500.00, 1),
('G-102', 'general', 1500.00, 1),
('G-103', 'general', 1500.00, 1),
('P-201', 'private', 4000.00, 1),
('P-202', 'private', 4000.00, 1),
('P-203', 'private', 5000.00, 1),
('ICU-1', 'icu',     12000.00, 1),
('ICU-2', 'icu',     12000.00, 1);

-- =============================================
-- TOKENS (today + past few days for sample data)
-- =============================================

INSERT INTO `tokens` (`token_number`, `doctor_id`, `token_date`) VALUES
(1, 1, CURDATE()),
(2, 1, CURDATE()),
(3, 1, CURDATE()),
(1, 2, CURDATE()),
(2, 2, CURDATE()),
(1, 3, CURDATE()),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(1, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY));

-- =============================================
-- PATIENTS (linked to tokens above)
-- =============================================

INSERT INTO `patients` (`token_id`, `serial_number`, `name`, `age`, `phone`, `gender`, `address`, `doctor_id`, `receptionist_id`, `visit_date`, `visit_time`, `notes`) VALUES
(1,  1, 'Muhammad Ali',      35, '0300-9876543', 'male',   'House 12, Block A, Gulshan, Karachi', 1, 2, CURDATE(),                           '09:15:00', 'Chest pain, referred by GP'),
(2,  2, 'Sara Khan',         28, '0301-8765432', 'female', 'Flat 5, Defence, Karachi',            1, 2, CURDATE(),                           '09:45:00', NULL),
(3,  3, 'Ahmed Raza',        45, '0302-7654321', 'male',   'Nazimabad, Karachi',                  1, 3, CURDATE(),                           '10:30:00', 'Follow-up visit'),
(4,  1, 'Fatima Bibi',        8, '0303-6543210', 'female', 'Orangi Town, Karachi',                2, 2, CURDATE(),                           '10:00:00', 'Fever and cough'),
(5,  2, 'Usman Tariq',       12, '0304-5432109', 'male',   'North Nazimabad, Karachi',            2, 3, CURDATE(),                           '10:30:00', NULL),
(6,  1, 'Khalid Sheikh',     52, '0305-4321098', 'male',   'PECHS, Karachi',                      3, 2, CURDATE(),                           '11:00:00', 'Knee pain, difficulty walking'),
(7,  1, 'Rafia Sultana',     38, NULL,           'female', 'Landhi, Karachi',                     1, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', NULL),
(8,  2, 'Naeem Butt',        61, '0306-3210987', 'male',   'Site Area, Karachi',                  1, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00', 'Hypertension follow-up'),
(9,  1, 'Amna Siddiqui',     25, '0307-2109876', 'female', 'Clifton, Karachi',                    2, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:00:00', NULL),
(10, 1, 'Tariq Hussain',     44, '0308-1098765', 'male',   'Malir, Karachi',                      4, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:15:00', NULL);

-- =============================================
-- PAYMENTS
-- =============================================

INSERT INTO `payments` (`patient_id`, `amount`, `status`, `payment_method`) VALUES
(1,  800.00, 'paid',   'cash'),
(2,  800.00, 'paid',   'card'),
(3,  800.00, 'unpaid', NULL),
(4,  600.00, 'paid',   'cash'),
(5,  600.00, 'unpaid', NULL),
(6,  900.00, 'paid',   'online'),
(7,  800.00, 'paid',   'cash'),
(8,  800.00, 'paid',   'insurance'),
(9,  600.00, 'paid',   'cash'),
(10, 700.00, 'unpaid', NULL);

-- =============================================
-- ADMISSIONS
-- =============================================

INSERT INTO `admissions`
  (`patient_name`, `guardian_name`, `gender`, `address`, `doctor_id`, `room_id`,
   `disease_name`, `disease_treatment_cost`, `admission_reason`, `room_fee_per_day`,
   `admitted_at`, `discharged_at`, `status`, `created_by`)
VALUES
('Shahid Mehmood',  'Ghulam Mehmood',  'male',   'Korangi, Karachi',        1, 4, 'Myocardial Infarction',      25000.00, 'emergency',   4000.00, DATE_SUB(NOW(), INTERVAL 5  DAY), NULL,                                 'admitted',  2),
('Rukhsana Begum',  'Aslam Khan',      'female', 'Baldia, Karachi',          4, 2, 'Normal Delivery',            10000.00, 'treatment',   1500.00, DATE_SUB(NOW(), INTERVAL 3  DAY), DATE_SUB(NOW(), INTERVAL 1 DAY),      'discharged', 2),
('Junaid Akhtar',   'Pervez Akhtar',   'male',   'Surjani Town, Karachi',   3, 5, 'Knee Replacement Surgery',   75000.00, 'operation',   4000.00, DATE_SUB(NOW(), INTERVAL 7  DAY), DATE_SUB(NOW(), INTERVAL 2 DAY),      'discharged', 3),
('Nasreen Sultana', 'Bashir Ahmed',    'female', 'Liaquatabad, Karachi',    5, 3, 'Typhoid Fever',               5000.00, 'observation', 1500.00, DATE_SUB(NOW(), INTERVAL 2  DAY), NULL,                                 'admitted',  3),
('Irfan Baig',      'Anwar Baig',      'male',   'FB Area, Karachi',         1, 7, 'Cardiac Arrhythmia',         40000.00, 'treatment',  12000.00, DATE_SUB(NOW(), INTERVAL 1  DAY), NULL,                                 'admitted',  2);
