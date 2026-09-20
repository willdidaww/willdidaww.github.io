-- =====================================================================
-- AKAP — Skema Supabase (PostgreSQL) — SLICE 1
-- Modul: auth/peran, master (pool/bus/rute/kru/kategori/standar), rit, pengeluaran.
--
-- Cara pakai: buka Supabase Dashboard > SQL Editor > jalankan file ini,
-- lalu policies.sql, lalu seed.sql. Lihat SETUP-SUPABASE.md.
-- =====================================================================

-- Ekstensi (biasanya sudah aktif di Supabase)
create extension if not exists "pgcrypto";

-- ---------------------------------------------------------------------
-- ENUM
-- ---------------------------------------------------------------------
do $$ begin
  create type peran_pengguna as enum ('owner','manajer','keuangan','admin_pool','kru');
exception when duplicate_object then null; end $$;

do $$ begin
  create type status_bus as enum ('aktif','nonaktif','perawatan');
exception when duplicate_object then null; end $$;

do $$ begin
  create type posisi_kru as enum ('sopir','sopir_2','kernet','kondektur');
exception when duplicate_object then null; end $$;

do $$ begin
  create type tipe_kategori as enum ('variabel','tetap','bbm');
exception when duplicate_object then null; end $$;

do $$ begin
  create type status_rit as enum ('rencana','berjalan','selesai','batal');
exception when duplicate_object then null; end $$;

do $$ begin
  create type status_pengeluaran as enum ('menunggu','disetujui','ditolak');
exception when duplicate_object then null; end $$;

do $$ begin
  create type sumber_input as enum ('admin','kru');
exception when duplicate_object then null; end $$;

-- ---------------------------------------------------------------------
-- POOL (cabang)
-- ---------------------------------------------------------------------
create table if not exists pool (
  id          bigint generated always as identity primary key,
  nama        text not null,
  kota        text,
  alamat      text,
  aktif       boolean not null default true,
  dibuat_pada timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- PROFILES — memetakan auth.users (Supabase Auth) ke peran & pool.
-- Baris dibuat otomatis via trigger saat user Auth baru terdaftar.
-- ---------------------------------------------------------------------
create table if not exists profiles (
  id          uuid primary key references auth.users(id) on delete cascade,
  pool_id     bigint references pool(id),
  nama        text not null default '',
  role        peran_pengguna not null default 'kru',
  kru_id      bigint,                       -- diisi bila role = kru
  aktif       boolean not null default true,
  dibuat_pada timestamptz not null default now()
);

-- Trigger: buat profile otomatis untuk user baru.
-- Metadata role/nama/pool bisa dikirim saat signup (raw_user_meta_data).
create or replace function handle_new_user()
returns trigger
language plpgsql
security definer set search_path = public
as $$
begin
  insert into public.profiles (id, nama, role, pool_id)
  values (
    new.id,
    coalesce(new.raw_user_meta_data->>'nama', split_part(new.email,'@',1)),
    coalesce((new.raw_user_meta_data->>'role')::peran_pengguna, 'kru'),
    nullif(new.raw_user_meta_data->>'pool_id','')::bigint
  )
  on conflict (id) do nothing;
  return new;
end; $$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function handle_new_user();

-- ---------------------------------------------------------------------
-- Helper: ambil role & pool user saat ini (dipakai oleh RLS)
-- SECURITY DEFINER agar bisa baca profiles tanpa memicu RLS rekursif.
-- ---------------------------------------------------------------------
create or replace function current_role_name()
returns peran_pengguna
language sql stable security definer set search_path = public
as $$ select role from profiles where id = auth.uid() $$;

create or replace function current_pool_id()
returns bigint
language sql stable security definer set search_path = public
as $$ select pool_id from profiles where id = auth.uid() $$;

create or replace function current_kru_id()
returns bigint
language sql stable security definer set search_path = public
as $$ select kru_id from profiles where id = auth.uid() $$;

-- true bila user termasuk staf kantor (bukan kru)
create or replace function is_staff()
returns boolean
language sql stable security definer set search_path = public
as $$ select coalesce(role in ('owner','manajer','keuangan','admin_pool'), false)
      from profiles where id = auth.uid() $$;

-- true bila user manajer/owner (boleh approve, edit setelah tutup, dsb)
create or replace function is_manager()
returns boolean
language sql stable security definer set search_path = public
as $$ select coalesce(role in ('owner','manajer'), false)
      from profiles where id = auth.uid() $$;

-- true bila baris pool boleh diakses user (owner/manajer lintas pool;
-- lainnya hanya pool sendiri). p_pool boleh null (data global).
create or replace function can_access_pool(p_pool bigint)
returns boolean
language sql stable security definer set search_path = public
as $$
  select case
    when auth.uid() is null then false
    when (select role from profiles where id = auth.uid()) in ('owner','manajer') then true
    when p_pool is null then true
    else p_pool = (select pool_id from profiles where id = auth.uid())
  end;
$$;

-- ---------------------------------------------------------------------
-- MASTER: BUS
-- ---------------------------------------------------------------------
create table if not exists bus (
  id          bigint generated always as identity primary key,
  pool_id     bigint not null references pool(id),
  nopol       text not null unique,
  kelas       text,
  karoseri    text,
  tahun       int,
  odometer    bigint not null default 0,
  status      status_bus not null default 'aktif',
  dibuat_pada timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- MASTER: RUTE
-- ---------------------------------------------------------------------
create table if not exists rute (
  id           bigint generated always as identity primary key,
  pool_id      bigint not null references pool(id),
  kode         text,
  asal         text not null,
  tujuan       text not null,
  jarak_km     numeric,
  estimasi_jam numeric,
  aktif        boolean not null default true,
  dibuat_pada  timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- MASTER: KRU
-- ---------------------------------------------------------------------
create table if not exists kru (
  id                 bigint generated always as identity primary key,
  pool_id            bigint not null references pool(id),
  nama               text not null,
  no_hp              text,
  posisi             posisi_kru not null default 'sopir',
  no_sim             text,
  sim_berlaku_sampai date,
  aktif              boolean not null default true,
  dibuat_pada        timestamptz not null default now()
);

-- profiles.kru_id -> kru.id (ditambah setelah kru ada)
do $$ begin
  alter table profiles
    add constraint profiles_kru_fk foreign key (kru_id) references kru(id);
exception when duplicate_object then null; end $$;

-- ---------------------------------------------------------------------
-- MASTER: KATEGORI BIAYA (hierarki + wajib bukti)
-- ---------------------------------------------------------------------
create table if not exists kategori_biaya (
  id          bigint generated always as identity primary key,
  induk_id    bigint references kategori_biaya(id),
  nama        text not null,
  tipe        tipe_kategori not null default 'variabel',
  wajib_bukti boolean not null default false,
  aktif       boolean not null default true,
  dibuat_pada timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- MASTER: STANDAR BIAYA per rute (riwayat berlaku)
-- ---------------------------------------------------------------------
create table if not exists standar_biaya_rute (
  id              bigint generated always as identity primary key,
  rute_id         bigint not null references rute(id),
  kategori_id     bigint not null references kategori_biaya(id),
  kelas_bus       text,
  nominal_standar numeric not null,
  toleransi_pct   numeric not null default 10,
  berlaku_dari    date not null default current_date,
  berlaku_sampai  date,
  dibuat_pada     timestamptz not null default now()
);

-- ---------------------------------------------------------------------
-- RIT (inti operasional)
-- ---------------------------------------------------------------------
create table if not exists rit (
  id                  bigint generated always as identity primary key,
  pool_id             bigint not null references pool(id),
  kode                text not null unique,
  bus_id              bigint not null references bus(id),
  rute_id             bigint not null references rute(id),
  tanggal             date not null,
  status              status_rit not null default 'rencana',
  km_awal             bigint,
  km_akhir            bigint,
  estimasi_uang_jalan numeric not null default 0,
  dibuat_oleh         uuid references auth.users(id),
  ditutup_oleh        uuid references auth.users(id),
  ditutup_pada        timestamptz,
  catatan             text,
  dibuat_pada         timestamptz not null default now()
);

-- Satu bus hanya boleh punya SATU rit aktif (rencana/berjalan)
create unique index if not exists idx_bus_rit_aktif
  on rit (bus_id) where status in ('rencana','berjalan');

create index if not exists idx_rit_status on rit(status);
create index if not exists idx_rit_pool on rit(pool_id);

-- Penugasan kru ke rit
create table if not exists rit_kru (
  id     bigint generated always as identity primary key,
  rit_id bigint not null references rit(id) on delete cascade,
  kru_id bigint not null references kru(id),
  peran  text not null default 'sopir',
  unique (rit_id, kru_id)
);

-- ---------------------------------------------------------------------
-- PENGELUARAN
-- ---------------------------------------------------------------------
create table if not exists pengeluaran (
  id             bigint generated always as identity primary key,
  pool_id        bigint not null references pool(id),
  rit_id         bigint references rit(id),
  kategori_id    bigint not null references kategori_biaya(id),
  nominal        numeric not null check (nominal > 0),
  tanggal        date not null,
  keterangan     text,
  odometer       bigint,
  liter          numeric,
  harga_liter    numeric,
  tangki_penuh   boolean,
  lampiran       text,                     -- URL Supabase Storage
  sumber         sumber_input not null default 'admin',
  client_uuid    text unique,              -- idempotency key dari HP kru
  status         status_pengeluaran not null default 'menunggu',
  flag_anomali   boolean not null default false,
  alasan_reject  text,
  disetujui_oleh uuid references auth.users(id),
  disetujui_pada timestamptz,
  dibuat_oleh    uuid references auth.users(id),
  dibuat_pada    timestamptz not null default now()
);

create index if not exists idx_pengeluaran_rit on pengeluaran(rit_id);
create index if not exists idx_pengeluaran_status on pengeluaran(status);
create index if not exists idx_pengeluaran_pool on pengeluaran(pool_id);

-- ---------------------------------------------------------------------
-- VIEW rekap rit (pendapatan belum ada di slice 1; biaya dari pengeluaran disetujui)
-- ---------------------------------------------------------------------
create or replace view v_rekap_rit as
select
  r.id as rit_id, r.kode, r.pool_id, r.tanggal, r.status, r.bus_id, r.rute_id,
  coalesce((select sum(e.nominal) from pengeluaran e
            where e.rit_id = r.id and e.status = 'disetujui'), 0) as total_biaya,
  case when r.km_awal is not null and r.km_akhir is not null and r.km_akhir > r.km_awal
       then r.km_akhir - r.km_awal else null end as jarak_tempuh_km
from rit r;
