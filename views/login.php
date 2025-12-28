<!-- Login View -->
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="card-title text-center mb-4">
                        <i class="bi bi-lock"></i> Admin Login
                    </h3>
                    
                    <?php if (isset($login_error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($login_error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Wachtwoord</label>
                            <input type="password" name="password" class="form-control" required autofocus placeholder="Voer wachtwoord in">
                        </div>
                        <button type="submit" name="login" value="1" class="btn btn-primary w-100">
                            <i class="bi bi-unlock"></i> Inloggen
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="?mode=reader" class="text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Terug naar Reader
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
