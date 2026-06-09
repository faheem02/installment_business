<?php
session_start();
$page_title = 'Edit Supplier';
$base_url = '../../';
require_once '../../includes/functions.php';
$id = (int)($_GET['id']??0);
$item = getById('suppliers', $id);
if (!$item) redirect('suppliers.php', 'Supplier not found', 'error');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    update('suppliers', [
        'name'=>$_POST['name'],'contact_person'=>$_POST['contact_person']??'','phone'=>$_POST['phone']??'',
        'email'=>$_POST['email']??'','address'=>$_POST['address']??'','city'=>$_POST['city']??'',
        'opening_balance'=>(float)($_POST['opening_balance']??0),
        'adjustment'=>(float)($_POST['adjustment']??0),
        'status'=>isset($_POST['status'])?1:0,'updated_at'=>date('Y-m-d')
    ], $id);
    redirect('suppliers.php', 'Supplier updated');
}
require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow mb-4">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-edit"></i> Edit Supplier</h6></div>
      <div class="card-body">
        <form method="post">
          <div class="row">
            <div class="col-md-6 form-group"><label>Company Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="<?=htmlspecialchars($item['name'])?>" required></div>
            <div class="col-md-6 form-group"><label>Contact Person</label><input type="text" name="contact_person" class="form-control" value="<?=htmlspecialchars($item['contact_person'])?>"></div>
          </div>
          <div class="row">
            <div class="col-md-4 form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?=htmlspecialchars($item['phone'])?>"></div>
            <div class="col-md-4 form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?=htmlspecialchars($item['email'])?>"></div>
            <div class="col-md-4 form-group"><label>City</label><input type="text" name="city" class="form-control" value="<?=htmlspecialchars($item['city'])?>"></div>
          </div>
          <div class="form-group"><label>Address</label><textarea name="address" class="form-control" rows="2"><?=htmlspecialchars($item['address'])?></textarea></div>
          <hr>
          <h6 class="font-weight-bold text-primary"><i class="fas fa-coins"></i> Financial Details</h6>
          <p class="small text-muted">Supplier amounts are credit by default (we owe the supplier).</p>
          <div class="row">
            <div class="col-md-6 form-group"><label>Opening Balance (Credit)</label><input type="number" name="opening_balance" class="form-control" step="0.01" value="<?=$item['opening_balance']?>"></div>
            <div class="col-md-6 form-group"><label>Adjustment <i class="fas fa-info-circle text-muted" title="Positive = we owe more, Negative = supplier owes us"></i></label><input type="number" name="adjustment" class="form-control" step="0.01" value="<?=$item['adjustment']?>" placeholder="+/- adjustment"></div>
          </div>
          <div class="form-group"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="s" name="status" <?=$item['status']?'checked':''?>><label class="custom-control-label" for="s">Active</label></div></div>
          <button type="submit" class="btn btn-primary btn-block py-2"><i class="fas fa-save"></i> Update</button>
          <a href="suppliers.php" class="btn btn-secondary btn-block">Cancel</a>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
