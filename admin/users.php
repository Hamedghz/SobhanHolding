<?php
require_once __DIR__ . '/../core/Auth.php'; require_once __DIR__ . '/../core/Database.php'; require_once __DIR__ . '/../core/Response.php'; require_once __DIR__ . '/../core/Validator.php';
Auth::requireRole('admin'); $pageTitle='مدیریت کاربران'; $edit=null;
if (isset($_GET['delete']) && Auth::verifyCsrf($_GET['csrf_token'] ?? '')) { Database::execute('DELETE FROM users WHERE id=? AND id<>?', [(int)$_GET['delete'], Auth::user()['id']]); flash('کاربر حذف شد.'); redirect('/admin/users.php'); }
if (isset($_GET['edit'])) $edit=Database::fetch('SELECT * FROM users WHERE id=?',[(int)$_GET['edit']]);
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if(!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) { flash('درخواست نامعتبر است.','danger'); redirect('/admin/users.php'); }
 $id=(int)($_POST['id']??0); $errors=Validator::required($_POST,['name'=>'نام','email'=>'ایمیل','username'=>'نام کاربری']); if(!Validator::email($_POST['email']??''))$errors['email']='ایمیل معتبر نیست.';
 if(!$errors){
  if($id){
   $params=[$_POST['name'],$_POST['email'],$_POST['username'],$_POST['role'],$_POST['status'],$id];
   $sql='UPDATE users SET name=?, email=?, username=?, role=?, status=?, updated_at=NOW() WHERE id=?';
   if(trim($_POST['password']??'')!==''){ $sql='UPDATE users SET name=?, email=?, username=?, role=?, status=?, password_hash=?, updated_at=NOW() WHERE id=?'; $params=[$_POST['name'],$_POST['email'],$_POST['username'],$_POST['role'],$_POST['status'],password_hash($_POST['password'],PASSWORD_DEFAULT),$id]; }
   Database::execute($sql,$params); flash('کاربر بروزرسانی شد.');
  } else { Database::execute('INSERT INTO users (name,email,username,password_hash,role,status,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())',[$_POST['name'],$_POST['email'],$_POST['username'],password_hash($_POST['password']?:bin2hex(random_bytes(6)),PASSWORD_DEFAULT),$_POST['role'],$_POST['status']]); flash('کاربر ایجاد شد.'); }
  redirect('/admin/users.php');
 } else flash(implode(' ', $errors),'danger');
}
$users=Database::fetchAll('SELECT * FROM users ORDER BY id DESC'); require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= e($edit['id']??'') ?>"><div class="grid grid-3"><label class="form-field"><span>نام</span><input name="name" value="<?= e($edit['name']??'') ?>" required></label><label class="form-field"><span>ایمیل</span><input type="email" name="email" value="<?= e($edit['email']??'') ?>" required></label><label class="form-field"><span>نام کاربری</span><input name="username" value="<?= e($edit['username']??'') ?>" required></label><label class="form-field"><span>رمز عبور <?= $edit?'(در صورت تغییر)':'' ?></span><input type="password" name="password" <?= $edit?'':'required' ?>></label><label class="form-field"><span>نقش</span><select name="role"><option value="manager" <?=($edit['role']??'')==='manager'?'selected':''?>>manager</option><option value="admin" <?=($edit['role']??'')==='admin'?'selected':''?>>admin</option></select></label><label class="form-field"><span>وضعیت</span><select name="status"><option value="active" <?=($edit['status']??'')==='active'?'selected':''?>>active</option><option value="disabled" <?=($edit['status']??'')==='disabled'?'selected':''?>>disabled</option></select></label></div><button class="btn btn-primary">ذخیره</button><a class="btn" href="/admin/users.php">جدید</a></form>
<div class="table-wrap"><table><thead><tr><th>نام</th><th>ایمیل</th><th>نام کاربری</th><th>نقش</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?=e($u['name'])?></td><td><?=e($u['email'])?></td><td><?=e($u['username'])?></td><td><?=e($u['role'])?></td><td><?=e($u['status'])?></td><td class="actions"><a class="btn btn-small" href="?edit=<?=$u['id']?>">ویرایش</a><a class="btn btn-small btn-danger" onclick="return confirm('حذف شود؟')" href="?delete=<?=$u['id']?>&csrf_token=<?=e(Auth::csrfToken())?>">حذف</a></td></tr><?php endforeach; ?></tbody></table></div>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
