-- =====================================================================
-- AKAP — Row Level Security (RLS) — SLICE 1
-- Menggantikan middleware peran PHP. Dijalankan SETELAH schema.sql.
--
-- Prinsip:
--  - Semua tabel RLS aktif; tanpa policy = tidak ada akses.
--  - Staf kantor (owner/manajer/keuangan/admin_pool) mengelola data pool-nya.
--    owner & manajer lintas pool; lainnya pool sendiri (fungsi can_access_pool).
--  - Kru: hanya BACA rit miliknya (via rit_kru) & pengeluaran miliknya,
--    serta INSERT pengeluaran ke rit miliknya. Tidak boleh approve.
--  - Approve/reject pengeluaran: staf saja (dibatasi di UPDATE policy).
-- =====================================================================

-- Aktifkan RLS
alter table pool                enable row level security;
alter table profiles            enable row level security;
alter table bus                 enable row level security;
alter table rute                enable row level security;
alter table kru                 enable row level security;
alter table kategori_biaya      enable row level security;
alter table standar_biaya_rute  enable row level security;
alter table rit                 enable row level security;
alter table rit_kru             enable row level security;
alter table pengeluaran         enable row level security;

-- Bersihkan policy lama (idempotent saat re-run)
do $$
declare r record;
begin
  for r in select schemaname, tablename, policyname from pg_policies where schemaname='public'
  loop execute format('drop policy if exists %I on %I.%I', r.policyname, r.schemaname, r.tablename); end loop;
end $$;

-- ---------------------------------------------------------------------
-- PROFILES: user lihat profilnya sendiri; staf lihat semua; owner kelola semua.
-- ---------------------------------------------------------------------
create policy profiles_select on profiles for select
  using (id = auth.uid() or is_staff());
create policy profiles_update_self on profiles for update
  using (id = auth.uid()) with check (id = auth.uid());
create policy profiles_owner_all on profiles for all
  using (current_role_name() = 'owner') with check (current_role_name() = 'owner');

-- ---------------------------------------------------------------------
-- POOL: staf boleh baca pool yang dapat diakses; owner kelola.
-- ---------------------------------------------------------------------
create policy pool_select on pool for select
  using (can_access_pool(id));
create policy pool_owner_write on pool for all
  using (current_role_name() = 'owner') with check (current_role_name() = 'owner');

-- ---------------------------------------------------------------------
-- Pola master data (bus/rute/kru/kategori/standar):
--  SELECT: staf yang boleh akses pool  (kru baca terbatas lewat join, lihat catatan)
--  WRITE : staf yang boleh akses pool; DELETE khusus manajer/owner
-- Kru butuh baca kategori & rute-nya; diberi SELECT read-only di bawah.
-- ---------------------------------------------------------------------

-- BUS
create policy bus_select on bus for select using (is_staff() and can_access_pool(pool_id));
create policy bus_insert on bus for insert with check (is_staff() and can_access_pool(pool_id));
create policy bus_update on bus for update using (is_staff() and can_access_pool(pool_id))
  with check (is_staff() and can_access_pool(pool_id));
create policy bus_delete on bus for delete using (is_manager() and can_access_pool(pool_id));

-- RUTE
create policy rute_select on rute for select using (is_staff() and can_access_pool(pool_id));
create policy rute_write on rute for all
  using (is_staff() and can_access_pool(pool_id))
  with check (is_staff() and can_access_pool(pool_id));

-- KRU
create policy kru_select on kru for select using (is_staff() and can_access_pool(pool_id));
create policy kru_write on kru for all
  using (is_staff() and can_access_pool(pool_id))
  with check (is_staff() and can_access_pool(pool_id));

-- KATEGORI BIAYA (global, tanpa pool). Semua user login boleh baca; staf kelola.
create policy kategori_select on kategori_biaya for select using (auth.uid() is not null);
create policy kategori_write on kategori_biaya for all
  using (is_staff()) with check (is_staff());

-- STANDAR BIAYA
create policy standar_select on standar_biaya_rute for select using (auth.uid() is not null);
create policy standar_write on standar_biaya_rute for all
  using (is_staff()) with check (is_staff());

-- ---------------------------------------------------------------------
-- RIT:
--  Staf: kelola rit pool-nya.
--  Kru : hanya SELECT rit yang dia ditugaskan (via rit_kru).
-- ---------------------------------------------------------------------
create policy rit_select_staff on rit for select
  using (is_staff() and can_access_pool(pool_id));
create policy rit_select_kru on rit for select
  using (
    current_role_name() = 'kru'
    and exists (select 1 from rit_kru rk where rk.rit_id = rit.id and rk.kru_id = current_kru_id())
  );
create policy rit_insert on rit for insert with check (is_staff() and can_access_pool(pool_id));
create policy rit_update on rit for update using (is_staff() and can_access_pool(pool_id))
  with check (is_staff() and can_access_pool(pool_id));
create policy rit_delete on rit for delete using (is_manager() and can_access_pool(pool_id));

-- RIT_KRU: staf kelola; kru boleh baca barisnya sendiri.
create policy rit_kru_select on rit_kru for select
  using (is_staff() or kru_id = current_kru_id());
create policy rit_kru_write on rit_kru for all
  using (is_staff()) with check (is_staff());

-- ---------------------------------------------------------------------
-- PENGELUARAN:
--  Staf: kelola pengeluaran pool-nya (termasuk approve via UPDATE).
--  Kru : SELECT & INSERT pengeluaran untuk rit miliknya (status awal 'menunggu'),
--        tidak boleh mengubah status (tidak diberi UPDATE).
-- ---------------------------------------------------------------------
create policy peng_select_staff on pengeluaran for select
  using (is_staff() and can_access_pool(pool_id));
create policy peng_select_kru on pengeluaran for select
  using (
    current_role_name() = 'kru'
    and exists (select 1 from rit_kru rk where rk.rit_id = pengeluaran.rit_id and rk.kru_id = current_kru_id())
  );

create policy peng_insert_staff on pengeluaran for insert
  with check (is_staff() and can_access_pool(pool_id));
create policy peng_insert_kru on pengeluaran for insert
  with check (
    current_role_name() = 'kru'
    and sumber = 'kru'
    and status = 'menunggu'
    and exists (select 1 from rit_kru rk
                join rit r on r.id = rk.rit_id
                where rk.rit_id = pengeluaran.rit_id
                  and rk.kru_id = current_kru_id()
                  and r.status in ('rencana','berjalan'))
  );

-- Update (termasuk approve/reject) hanya staf.
create policy peng_update_staff on pengeluaran for update
  using (is_staff() and can_access_pool(pool_id))
  with check (is_staff() and can_access_pool(pool_id));
create policy peng_delete_staff on pengeluaran for delete
  using (is_staff() and can_access_pool(pool_id));
