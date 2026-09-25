<?php
/** @var string $next page to return to after signing in */
?>
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="loginTitle">เข้าสู่ระบบ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <form class="modal-body" method="post" action="login.php">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="mb-3">
          <label class="form-label" for="login_email">อีเมล</label>
          <input type="email" id="login_email" name="email" class="form-control" required autocomplete="email">
        </div>
        <div class="mb-3">
          <label class="form-label" for="login_password">รหัสผ่าน</label>
          <input type="password" id="login_password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary w-100" type="submit">เข้าสู่ระบบ</button>
        <button type="button" class="btn btn-ghost w-100 mt-2" data-bs-target="#registerModal" data-bs-toggle="modal">ยังไม่มีบัญชี? สมัครสมาชิก</button>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="registerTitle">สมัครสมาชิก</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <form class="modal-body" method="post" action="register.php" autocomplete="off">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="mb-3">
          <label class="form-label" for="reg_username">ชื่อผู้ใช้</label>
          <input type="text" id="reg_username" name="username" class="form-control" required maxlength="100">
        </div>
        <div class="mb-3">
          <label class="form-label" for="reg_email">อีเมล</label>
          <input type="email" id="reg_email" name="email" class="form-control" required>
          <div class="form-text">ใช้ได้เฉพาะ @gmail.com หรือ @hotmail.com</div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label" for="reg_password">รหัสผ่าน</label>
            <input type="password" id="reg_password" name="password" class="form-control" required autocomplete="new-password">
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="reg_confirm_password">ยืนยันรหัสผ่าน</label>
            <input type="password" id="reg_confirm_password" name="confirm_password" class="form-control" required autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">สมัครสมาชิก</button>
        <button type="button" class="btn btn-ghost w-100 mt-2" data-bs-target="#loginModal" data-bs-toggle="modal">มีบัญชีแล้ว? เข้าสู่ระบบ</button>
      </form>
    </div>
  </div>
</div>
