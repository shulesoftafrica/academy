<?php
/**
 * Assessment checkout — mirrors the talent platform's payment page: an order
 * summary + payment-method cards (mobile-money control number with a copy
 * button, card via Stripe/Flutterwave) + a "waiting for confirmation" panel.
 * Self-contained styling (.pay-) so it doesn't inherit the LMS theme's card
 * quirks. Payment options load asynchronously (checkout_gateways).
 */
$amount = $product['currency'] . ' ' . number_format((float) $product['price'], $product['currency'] === 'TZS' ? 0 : 2);
?>
<style>
  .pay-wrap { --t:#0e9f8e; --td:#0b8577; --tint:#e9f7f4; --ink:#152625; --mut:#66807c;
              --line:#e7edeb; --bg:#f6f9f8; --amber-bg:#fff4e5; --amber:#b26a00; }
  .pay-wrap { background:var(--bg); min-height:70vh; padding:40px 0; }
  .pay-col { max-width:760px; margin:0 auto; padding:0 16px; }
  .pay-h { font-weight:800; font-size:1.5rem; color:var(--ink); margin:0 0 4px; letter-spacing:-.01em; }
  .pay-sub { color:var(--mut); margin:0 0 22px; font-size:.95rem; }
  .pay-card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:22px 24px; margin-bottom:16px;
              box-shadow:0 1px 2px rgba(21,38,37,.04); }
  .pay-card-title { font-weight:700; color:var(--ink); font-size:1.02rem; margin:0 0 14px; }
  .pay-row { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:7px 0; font-size:.95rem; }
  .pay-row + .pay-row { border-top:1px solid #f1f5f4; }
  .pay-row .k { color:var(--mut); } .pay-row .v { font-weight:700; color:var(--ink); text-align:right; }
  .pay-amt { font-weight:800; color:var(--td); font-size:1.15rem; }
  .pay-pill { border-radius:999px; padding:4px 12px; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.03em; }
  .pay-pill.pending { background:var(--amber-bg); color:var(--amber); }
  .pay-pill.paid { background:var(--tint); color:var(--td); }

  .pay-methods { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:14px; }
  .pay-method { border:1px solid var(--line); border-radius:14px; padding:18px; text-align:center; display:flex; flex-direction:column; align-items:center; }
  .pay-method .ic { width:46px; height:46px; border-radius:12px; background:var(--tint); color:var(--td);
                    display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom:10px; }
  .pay-method .mt { font-weight:700; color:var(--ink); font-size:.98rem; }
  .pay-method .ms { color:var(--mut); font-size:.8rem; margin:2px 0 14px; }
  .pay-ucn-box { width:100%; border:2px dashed var(--t); background:var(--tint); border-radius:12px; padding:12px; margin-bottom:12px; }
  .pay-ucn-lbl { font-size:.72rem; font-weight:700; color:var(--mut); text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
  .pay-ucn-num { font-weight:800; font-size:1.7rem; letter-spacing:3px; color:var(--td); font-variant-numeric:tabular-nums; }
  .pay-btn { display:block; width:100%; border:none; border-radius:10px; padding:11px 14px; font-weight:700; font-size:.9rem;
             text-decoration:none; text-align:center; cursor:pointer; transition:.15s; }
  .pay-btn-solid { background:var(--t); color:#fff; } .pay-btn-solid:hover { background:var(--td); color:#fff; }
  .pay-btn-copy { background:var(--t); color:#fff; padding:8px 12px; font-size:.82rem; border-radius:8px; }
  .pay-amt-box { width:100%; background:#f4f7f6; border-radius:10px; padding:10px; margin-bottom:12px; }
  .pay-amt-box .l { font-size:.72rem; font-weight:700; color:var(--mut); text-transform:uppercase; }
  .pay-amt-box .a { font-weight:800; color:var(--ink); font-size:1.05rem; }
  .pay-loading { text-align:center; padding:26px 0; color:var(--mut); }
  .pay-note { color:var(--mut); font-size:.82rem; margin:10px 0 0; }
  .pay-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:4px; }
  .pay-actions > * { flex:1; min-width:180px; }
  .pay-btn-ghost { background:#fff; border:1px solid var(--line); color:var(--ink); }
  .pay-btn-ghost:hover { border-color:var(--t); color:var(--td); }
  .pay-steps { margin:12px 0 0; padding-left:18px; color:var(--mut); font-size:.85rem; }
  .pay-steps li { margin:3px 0; }
</style>

<section class="pay-wrap">
  <div class="pay-col">
    <h1 class="pay-h"><?php echo get_phrase('Complete your payment'); ?></h1>
    <p class="pay-sub"><?php echo html_escape($skill['name']); ?> — <?php echo html_escape($level['name']); ?> <?php echo get_phrase('assessment'); ?></p>

    <!-- Order summary -->
    <div class="pay-card">
      <div class="pay-card-title"><?php echo get_phrase('Order summary'); ?></div>
      <div class="pay-row"><span class="k"><?php echo get_phrase('Assessment'); ?></span><span class="v"><?php echo html_escape($skill['name']); ?> — <?php echo html_escape($level['name']); ?></span></div>
      <div class="pay-row"><span class="k"><?php echo get_phrase('Amount'); ?></span><span class="v pay-amt"><?php echo $amount; ?></span></div>
      <div class="pay-row"><span class="k"><?php echo get_phrase('Invoice number'); ?></span><span class="v" id="pay-invoice">…</span></div>
      <div class="pay-row"><span class="k"><?php echo get_phrase('Status'); ?></span><span class="pay-pill pending" id="pay-status"><?php echo get_phrase('Awaiting payment'); ?></span></div>
    </div>

    <!-- Payment methods -->
    <div class="pay-card">
      <div class="pay-card-title"><?php echo get_phrase('How would you like to pay?'); ?></div>

      <div id="pay-loading" class="pay-loading">
        <div class="spinner-border" style="color:#0e9f8e;" role="status"></div>
        <div class="mt-2"><?php echo get_phrase('Preparing your payment options…'); ?></div>
      </div>

      <div id="pay-methods" class="pay-methods" style="display:none;">
        <!-- Mobile money / bank (UCN) -->
        <div class="pay-method" id="m-ucn" style="display:none;">
          <div class="ic"><i class="fas fa-university"></i></div>
          <div class="mt"><?php echo get_phrase('Mobile money / Bank'); ?></div>
          <div class="ms"><?php echo get_phrase('M-Pesa, Tigo Pesa, Airtel, CRDB, NMB'); ?></div>
          <div class="pay-ucn-box">
            <div class="pay-ucn-lbl"><?php echo get_phrase('Enter this control number'); ?></div>
            <div class="pay-ucn-num" id="ucn-num"></div>
          </div>
          <button type="button" class="pay-btn pay-btn-copy" id="ucn-copy"><i class="fas fa-copy"></i> <?php echo get_phrase('Copy number'); ?></button>
          <ol class="pay-steps text-start">
            <li><?php echo get_phrase('Open your mobile money or bank app'); ?></li>
            <li><?php echo get_phrase('Choose Pay Bill / Company, then paste this number'); ?></li>
            <li><?php echo get_phrase('Pay'); ?> <strong><?php echo $amount; ?></strong> <?php echo get_phrase('and confirm'); ?></li>
          </ol>
        </div>

        <!-- Card via Stripe -->
        <a class="pay-method" id="m-stripe" href="#" target="_blank" rel="noopener" style="display:none;text-decoration:none;">
          <div class="ic"><i class="fas fa-credit-card"></i></div>
          <div class="mt"><?php echo get_phrase('Pay by card'); ?></div>
          <div class="ms"><?php echo get_phrase('Visa / Mastercard (Stripe)'); ?></div>
          <div class="pay-amt-box"><div class="l"><?php echo get_phrase('Amount'); ?></div><div class="a"><?php echo $amount; ?></div></div>
          <span class="pay-btn pay-btn-solid"><?php echo get_phrase('Continue to card payment'); ?></span>
        </a>

        <!-- Flutterwave -->
        <a class="pay-method" id="m-flutter" href="#" target="_blank" rel="noopener" style="display:none;text-decoration:none;">
          <div class="ic"><i class="fas fa-mobile-alt"></i></div>
          <div class="mt"><?php echo get_phrase('Pay with Flutterwave'); ?></div>
          <div class="ms"><?php echo get_phrase('Card & mobile money'); ?></div>
          <div class="pay-amt-box"><div class="l"><?php echo get_phrase('Amount'); ?></div><div class="a"><?php echo $amount; ?></div></div>
          <span class="pay-btn pay-btn-solid"><?php echo get_phrase('Continue to Flutterwave'); ?></span>
        </a>
      </div>

      <div id="pay-none" class="alert alert-warning mt-2" style="display:none;">
        <?php echo get_phrase('Payment options are taking a moment.'); ?>
        <a href="javascript:;" onclick="loadGateways()" class="alert-link"><?php echo get_phrase('Retry'); ?></a>
      </div>
    </div>

    <!-- Waiting / confirm -->
    <div class="pay-card">
      <div class="pay-card-title"><?php echo get_phrase('Already paid?'); ?></div>
      <p class="pay-note mb-3" style="margin-top:0;"><?php echo get_phrase('Your assessment starts automatically once we confirm your payment. It can take a minute after you pay.'); ?></p>
      <div class="pay-actions">
        <a href="<?php echo site_url('skills/checkout_status/' . $payment_id); ?>" class="pay-btn pay-btn-ghost"><i class="fas fa-rotate-right"></i> <?php echo get_phrase('Refresh status'); ?></a>
        <a href="<?php echo site_url('skills/checkout_status/' . $payment_id); ?>" class="pay-btn pay-btn-solid"><?php echo get_phrase('I have paid — check status'); ?></a>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  var url = '<?php echo site_url('skills/checkout_gateways/' . $payment_id); ?>';
  window.loadGateways = function () {
    document.getElementById('pay-loading').style.display = 'block';
    document.getElementById('pay-methods').style.display = 'none';
    document.getElementById('pay-none').style.display = 'none';
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (g) {
        document.getElementById('pay-loading').style.display = 'none';
        if (g && g.invoice_id) { document.getElementById('pay-invoice').textContent = '#' + g.invoice_id; }
        if (g && g.ok === false) { document.getElementById('pay-none').style.display = 'block'; return; }
        var any = false;
        if (g.ucn) {
          any = true;
          document.getElementById('ucn-num').textContent = g.ucn;
          document.getElementById('m-ucn').style.display = 'flex';
          document.getElementById('ucn-copy').addEventListener('click', function () {
            var btn = this;
            navigator.clipboard.writeText(g.ucn).then(function () {
              btn.innerHTML = '✓ <?php echo get_phrase('Copied'); ?>';
              setTimeout(function () { btn.innerHTML = '<i class="fas fa-copy"></i> <?php echo get_phrase('Copy number'); ?>'; }, 2000);
            });
          });
        }
        if (g.stripe_link) { any = true; var s = document.getElementById('m-stripe'); s.href = g.stripe_link; s.style.display = 'flex'; }
        if (g.flutterwave_link) { any = true; var f = document.getElementById('m-flutter'); f.href = g.flutterwave_link; f.style.display = 'flex'; }
        if (any) { document.getElementById('pay-methods').style.display = 'grid'; }
        else { document.getElementById('pay-none').style.display = 'block'; }
      })
      .catch(function () {
        document.getElementById('pay-loading').style.display = 'none';
        document.getElementById('pay-none').style.display = 'block';
      });
  };
  loadGateways();
})();
</script>
