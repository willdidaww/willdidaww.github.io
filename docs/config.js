// ====================================================================
// Konfigurasi Supabase untuk deploy GitHub Pages.
//   Supabase Dashboard > Project Settings > API
//     - "Project URL"      -> SUPABASE_URL
//     - "anon public" key  -> SUPABASE_ANON_KEY
//
// Aman dipublikasikan: anon key memang untuk dipakai di browser;
// Row Level Security (RLS) yang mengamankan data. JANGAN pakai service_role.
// ====================================================================
window.AKAP_CONFIG = {
  SUPABASE_URL: 'https://jpejijudagbcmontygdr.supabase.co',
  SUPABASE_ANON_KEY: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImpwZWppanVkYWdiY21vbnR5Z2RyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODk5MTAxNjYsImV4cCI6MjEwNTQ4NjE2Nn0.Fl5IQJYCiRI_TMRxCoObQ9l69xmcx_jj2BpUGYu7CeA',
};
