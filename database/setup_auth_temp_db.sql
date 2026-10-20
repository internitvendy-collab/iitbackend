-- Legacy setup script. OTP data now lives in the main database.
-- Prefer `php artisan migrate` so the schema stays aligned with the application.
USE iit_shelf;

-- Temporary user verification table (OTP codes for registration and password reset)
CREATE TABLE IF NOT EXISTS Temp_User_Verification (
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(20) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (email, purpose),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
);
