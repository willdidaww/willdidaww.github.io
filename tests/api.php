<?php
declare(strict_types=1);
/** Test API kru: idempotency client_uuid, scoping, anomali. */
$root = dirname(__DIR__);
require $root . '/src/bootstrap.php';
require $root . '/src/Support/helpers.php';
\App\Core\View::setPath($root . '/resources/views');

use App\Core\{Request, Router, Session, App};
$router = new Router();
(require $root . '/routes/web.php')($router);
@Session::start();
restore_exception_handler(); restore_error_handler();

$pass=0;$fail=0;
function c(string $l,bool $ok,string $x=''){global $pass,$fail;echo($ok?"  ✓ ":"  ✗ ").$l.($x?" — $x":"")."\n";$ok?$pass++:$fail++;}
function jreq(Router $r,string $m,string $p,array $b){$q=new Request();$q->method=$m;$q->path=$p;$q->body=$b;$q->query=[];$q->files=[];$_SERVER['HTTP_X_REQUESTED_WITH']='XMLHttpRequest';return $r->dispatch($q);}

$db = App::db();

// siapkan: login owner, buat bus+rit+assign kru, cairkan; lalu login kru & pakai API
$csrf = Session::csrfToken();
jreq($router,'POST','/login',['identifier'=>'owner@akap.test','password'=>'password','_csrf'=>$csrf]);
$busId=(int)$db->scalar("SELECT id FROM bus WHERE status='aktif' AND id NOT IN (SELECT bus_id FROM rit WHERE status IN ('rencana','berjalan')) LIMIT 1");
$ruteId=(int)$db->scalar("SELECT id FROM rute LIMIT 1");
$kruId=(int)$db->scalar("SELECT kru_id FROM pengguna WHERE email='kru@akap.test'");
jreq($router,'POST','/rit',['bus_id'=>$busId,'rute_id'=>$ruteId,'tanggal'=>date('Y-m-d'),'kru_ids'=>[$kruId],'_csrf'=>$csrf]);
$ritId=(int)$db->scalar("SELECT id FROM rit ORDER BY id DESC LIMIT 1");
c('setup rit dgn kru', $ritId>0 && (int)$db->scalar("SELECT COUNT(*) FROM rit_kru WHERE rit_id=? AND kru_id=?",[$ritId,$kruId])===1);

// login kru
$_SESSION=[]; $csrf=Session::csrfToken();
jreq($router,'POST','/login',['identifier'=>'kru@akap.test','password'=>'password','_csrf'=>$csrf]);
c('login kru', (Session::get('user')['role']??'')==='kru');

$kat=(int)$db->scalar("SELECT id FROM kategori_biaya WHERE wajib_bukti=0 LIMIT 1");
$uuid='test-uuid-123';
$payload=['client_uuid'=>$uuid,'rit_id'=>$ritId,'kategori_id'=>$kat,'nominal'=>75000,'tanggal'=>date('Y-m-d'),'_csrf'=>$csrf];

$res=jreq($router,'POST','/api/kru/pengeluaran',$payload);
$body=json_decode($res->body,true);
c('API simpan pengeluaran ok', $res->status===200 && ($body['ok']??false), 'status='.$res->status);
$cnt1=(int)$db->scalar("SELECT COUNT(*) FROM pengeluaran WHERE client_uuid=?",[$uuid]);
c('1 baris tersimpan', $cnt1===1);

// kirim ulang uuid sama -> idempotent, tidak dobel
$res=jreq($router,'POST','/api/kru/pengeluaran',$payload);
$body=json_decode($res->body,true);
$cnt2=(int)$db->scalar("SELECT COUNT(*) FROM pengeluaran WHERE client_uuid=?",[$uuid]);
c('idempotent: tetap 1 baris', $cnt2===1 && ($body['duplikat']??false));

// scoping: kru coba akses rit orang lain (rit_id yg tak ada di rit_kru-nya)
$ritLain=(int)$db->scalar("SELECT r.id FROM rit r WHERE r.id NOT IN (SELECT rit_id FROM rit_kru WHERE kru_id=?) LIMIT 1",[$kruId]);
if ($ritLain) {
  $res=jreq($router,'POST','/api/kru/pengeluaran',['client_uuid'=>'x-'.uniqid(),'rit_id'=>$ritLain,'kategori_id'=>$kat,'nominal'=>1000,'_csrf'=>$csrf]);
  c('kru ditolak input ke rit bukan miliknya -> 403', $res->status===403, 'status='.$res->status);
} else { c('(skip) tidak ada rit lain untuk uji scoping', true); }

// anomali: owner tetapkan standar utk kategori non-bukti, lalu input 2x standar
$_SESSION=[]; $csrf=Session::csrfToken();
jreq($router,'POST','/login',['identifier'=>'owner@akap.test','password'=>'password','_csrf'=>$csrf]);
$katNoBukti=(int)$db->scalar("SELECT id FROM kategori_biaya WHERE wajib_bukti=0 AND tipe='variabel' LIMIT 1");
// tetapkan standar 100rb, toleransi 10% pada rute rit
jreq($router,'POST','/standar-biaya',['rute_id'=>$ruteId,'kategori_id'=>$katNoBukti,'nominal_standar'=>100000,'toleransi_pct'=>10,'_csrf'=>$csrf]);
$stdOk=(int)$db->scalar("SELECT COUNT(*) FROM standar_biaya_rute WHERE rute_id=? AND kategori_id=? AND berlaku_sampai IS NULL",[$ruteId,$katNoBukti]);
c('standar non-bukti dibuat', $stdOk===1);
jreq($router,'POST','/pengeluaran',['rit_id'=>$ritId,'kategori_id'=>$katNoBukti,'nominal'=>250000,'tanggal'=>date('Y-m-d'),'_csrf'=>$csrf]);
$flag=(int)$db->scalar("SELECT flag_anomali FROM pengeluaran WHERE rit_id=? AND kategori_id=? ORDER BY id DESC LIMIT 1",[$ritId,$katNoBukti]);
c('pengeluaran 250rb vs standar 100rb+10% ditandai anomali', $flag===1);
// kontrol: nominal dalam toleransi tidak ditandai
jreq($router,'POST','/pengeluaran',['rit_id'=>$ritId,'kategori_id'=>$katNoBukti,'nominal'=>105000,'tanggal'=>date('Y-m-d'),'_csrf'=>$csrf]);
$flag2=(int)$db->scalar("SELECT flag_anomali FROM pengeluaran WHERE rit_id=? AND kategori_id=? ORDER BY id DESC LIMIT 1",[$ritId,$katNoBukti]);
c('pengeluaran 105rb (dalam toleransi) tidak anomali', $flag2===0);

echo "\n==== API: $pass lulus, $fail gagal ====\n";
exit($fail>0?1:0);
