<?php if(get_frontend_settings('recaptcha_status')): ?>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>

<!---------- Header Section End  ---------->
<section class="sign-up my-5 pt-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-7 col-md-6 col-sm-12 col-12 text-center">
                <img loading="lazy" width="65%" src="<?php echo site_url('assets/frontend/default-new/image/login-security.gif') ?>">
            </div>
            <div class="col-lg-5 col-md-6 col-sm-12 col-12 ">
                <div class="sing-up-right">
                    <h3><?php echo get_phrase('Log In'); ?><span>!</span></h3>
                    <p><?php echo get_phrase('Sign in with a one-time code. Open to the ShuleSoft community only.'); ?></p>

                    <?php $otp_step = isset($otp_step) ? $otp_step : 1; ?>

                    <?php if ($otp_step == 1): ?>
                        <!-- Step 1 — identifier -->
                        <form action="<?php echo site_url('login/request_otp'); ?>" method="post">
                            <div class="mb-4">
                                <h5><?php echo get_phrase('Email or WhatsApp phone'); ?></h5>
                                <div class="position-relative">
                                    <i class="fa-solid fa-user"></i>
                                    <input class="form-control" type="text" name="identifier" required
                                           placeholder="<?php echo get_phrase('you@example.com  or  07XXXXXXXX'); ?>">
                                </div>
                                <small class="w-100 text-muted"><?php echo get_phrase('We will send a one-time code to your email, or to WhatsApp if you enter a phone number.'); ?></small>
                            </div>
                            <div class="log-in">
                                <button type="submit" class="btn btn-primary w-100"><?php echo get_phrase('Send code'); ?></button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Step 2 — verify code -->
                        <form action="<?php echo site_url('login/verify_otp'); ?>" method="post">
                            <div class="mb-3">
                                <h5><?php echo get_phrase('Enter the 6-digit code'); ?></h5>
                                <p class="text-muted mb-2" style="font-size:13px;">
                                    <?php echo get_phrase('Sent to'); ?>
                                    <strong><?php echo html_escape(isset($otp_identifier) ? $otp_identifier : ''); ?></strong>
                                    (<?php echo (isset($otp_channel) && $otp_channel == 'whatsapp') ? 'WhatsApp' : get_phrase('email'); ?>)
                                </p>
                                <div class="position-relative">
                                    <i class="fa-solid fa-key"></i>
                                    <input class="form-control" type="text" name="code" inputmode="numeric" pattern="[0-9]*"
                                           maxlength="6" autocomplete="one-time-code" required autofocus
                                           placeholder="––––––" style="letter-spacing:6px;">
                                </div>
                            </div>
                            <div class="log-in">
                                <button type="submit" class="btn btn-primary w-100"><?php echo get_phrase('Verify & log in'); ?></button>
                            </div>
                        </form>
                        <div class="another text-center mt-3">
                            <p style="font-size:13px;">
                                <a href="<?php echo site_url('login/resend_otp'); ?>"><?php echo get_phrase('Resend code'); ?></a>
                                &nbsp;·&nbsp;
                                <a href="<?php echo site_url('login/reset_otp'); ?>"><?php echo get_phrase('Use a different email/phone'); ?></a>
                            </p>
                        </div>
                    <?php endif; ?>

                    <p class="text-center text-muted mt-4" style="font-size:12px;">
                        <?php echo get_phrase('Only members of the ShuleSoft community (ShuleSoft, Talent, SafariBook) can access this platform.'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    function onLoginSubmit(token) {
        document.getElementById("login-form").submit();
    }
</script>