// Salin file ini menjadi `config.js`, lalu isi dengan nilai proyek Supabase Anda.
// Ambil dari: Supabase Dashboard > Project Settings > API.
//  - Project URL       -> SUPABASE_URL
//  - anon public key    -> SUPABASE_ANON_KEY  (aman dipakai di browser; RLS yang mengamankan data)
//
// JANGAN memasukkan service_role key di sini — itu key rahasia, tidak boleh di front-end.
window.AKAP_CONFIG = {
  SUPABASE_URL: 'https://YOUR-PROJECT-ref.supabase.co',
  SUPABASE_ANON_KEY: 'YOUR-ANON-PUBLIC-KEY',
};
