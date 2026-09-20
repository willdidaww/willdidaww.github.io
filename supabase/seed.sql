-- =====================================================================
-- AKAP — Seed data awal — SLICE 1
-- Jalankan SETELAH schema.sql & policies.sql.
--
-- CATATAN PENTING soal AKUN:
--   Akun login TIDAK dibuat lewat SQL (password di-hash oleh Supabase Auth).
--   Buat user di Dashboard > Authentication > Users (atau via signup),
--   lalu jalankan blok "PROMOSI PERAN" di bawah untuk menetapkan role/pool/kru.
--   Trigger handle_new_user() otomatis membuat baris profiles saat user dibuat.
-- =====================================================================

-- Idempotent: hanya seed bila pool masih kosong
do $$
declare v_pool bigint;
declare v_cat_ops bigint; declare v_bbm bigint; declare v_cat_tetap bigint; declare v_cat_rawat bigint;
declare v_bus1 bigint; declare v_bus2 bigint;
declare v_rute1 bigint; declare v_rute2 bigint;
declare v_kru1 bigint;
begin
  if exists (select 1 from pool) then
    raise notice 'Seed dilewati — data sudah ada.';
    return;
  end if;

  insert into pool (nama, kota, alamat) values
    ('Pool Pusat Jakarta','Jakarta','Jl. Terminal Pulogebang No. 1')
    returning id into v_pool;

  -- Kategori biaya (hierarki + wajib bukti)
  insert into kategori_biaya (nama, tipe, wajib_bukti) values ('Biaya Operasional Rit','variabel',false)
    returning id into v_cat_ops;
  insert into kategori_biaya (induk_id, nama, tipe, wajib_bukti) values (v_cat_ops,'BBM (Solar)','bbm',true)
    returning id into v_bbm;
  insert into kategori_biaya (induk_id, nama, tipe, wajib_bukti) values
    (v_cat_ops,'Tol','variabel',true),
    (v_cat_ops,'Retribusi Terminal','variabel',false),
    (v_cat_ops,'Uang Makan Kru','variabel',false),
    (v_cat_ops,'Parkir','variabel',false);
  insert into kategori_biaya (nama, tipe) values ('Biaya Tetap','tetap') returning id into v_cat_tetap;
  insert into kategori_biaya (induk_id, nama, tipe) values (v_cat_tetap,'Gaji Kru','tetap');
  insert into kategori_biaya (nama, tipe, wajib_bukti) values ('Perawatan','variabel',true) returning id into v_cat_rawat;
  insert into kategori_biaya (induk_id, nama, tipe, wajib_bukti) values
    (v_cat_rawat,'Servis Berkala','variabel',true),
    (v_cat_rawat,'Ganti Ban','variabel',true);

  -- Bus
  insert into bus (pool_id,nopol,kelas,karoseri,tahun,odometer,status) values
    (v_pool,'B 7001 AKAP','eksekutif','Adiputro',2021,340000,'aktif') returning id into v_bus1;
  insert into bus (pool_id,nopol,kelas,karoseri,tahun,odometer,status) values
    (v_pool,'B 7002 AKAP','bisnis','Laksana',2019,510000,'aktif') returning id into v_bus2;
  insert into bus (pool_id,nopol,kelas,karoseri,tahun,odometer,status) values
    (v_pool,'B 7003 AKAP','ekonomi','Tentrem',2018,620000,'perawatan');

  -- Rute
  insert into rute (pool_id,kode,asal,tujuan,jarak_km,estimasi_jam) values
    (v_pool,'JKT-SBY','Jakarta','Surabaya',780,12) returning id into v_rute1;
  insert into rute (pool_id,kode,asal,tujuan,jarak_km,estimasi_jam) values
    (v_pool,'JKT-JOG','Jakarta','Yogyakarta',560,9) returning id into v_rute2;

  -- Kru
  insert into kru (pool_id,nama,no_hp,posisi,no_sim,sim_berlaku_sampai) values
    (v_pool,'Sukirman','081200000001','sopir','SIM-B2-001', current_date + 40) returning id into v_kru1;
  insert into kru (pool_id,nama,no_hp,posisi,no_sim,sim_berlaku_sampai) values
    (v_pool,'Bambang','081200000002','sopir_2','SIM-B2-002', current_date + 400);
  insert into kru (pool_id,nama,no_hp,posisi) values (v_pool,'Joko','081200000003','kernet');

  -- Standar biaya (BBM per rute)
  insert into standar_biaya_rute (rute_id,kategori_id,kelas_bus,nominal_standar,toleransi_pct) values
    (v_rute1,v_bbm,null,3200000,10),
    (v_rute2,v_bbm,null,2300000,10);

  raise notice 'Seed selesai. Pool id=%, kru pertama id=% (untuk ditautkan ke akun kru).', v_pool, v_kru1;
end $$;

-- =====================================================================
-- PROMOSI PERAN — jalankan SETELAH membuat user di Authentication.
-- Ganti email sesuai user yang Anda buat. Aman dijalankan berulang.
--
-- Contoh: set owner@akap.test jadi owner pada pool pertama.
-- =====================================================================
-- update profiles p set role='owner',      nama='Owner Utama',    pool_id=(select id from pool order by id limit 1)
--   where p.id = (select id from auth.users where email='owner@akap.test');
-- update profiles p set role='manajer',     nama='Manajer Ops',    pool_id=(select id from pool order by id limit 1)
--   where p.id = (select id from auth.users where email='manajer@akap.test');
-- update profiles p set role='keuangan',    nama='Staff Keuangan', pool_id=(select id from pool order by id limit 1)
--   where p.id = (select id from auth.users where email='keuangan@akap.test');
-- update profiles p set role='admin_pool',  nama='Admin Pool',     pool_id=(select id from pool order by id limit 1)
--   where p.id = (select id from auth.users where email='admin@akap.test');
-- update profiles p set role='kru',         nama='Sukirman (Kru)', pool_id=(select id from pool order by id limit 1),
--        kru_id=(select id from kru where nama='Sukirman' limit 1)
--   where p.id = (select id from auth.users where email='kru@akap.test');
