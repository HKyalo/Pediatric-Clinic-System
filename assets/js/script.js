// ============================================
// PCASS - Password Strength Validation
// ============================================

/**
 * Checks password strength against security requirements
 * Updates visual indicators in real-time
 * 
 * @param {string} password - The password to check
 * @returns {object} - Validation results
 */
function checkPasswordStrength(password) {
    // Define password requirements
    const requirements = {
        minLength: password.length >= 8,
        hasUpper: /[A-Z]/.test(password),
        hasLower: /[a-z]/.test(password),
        hasNumber: /[0-9]/.test(password),
        hasSpecial: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
    };
    
    // Update visual indicators if they exist on the page
    if (document.getElementById('req-length')) {
        document.getElementById('req-length').className = 
            requirements.minLength ? 'strength-item valid' : 'strength-item invalid';
        
        document.getElementById('req-upper').className = 
            requirements.hasUpper ? 'strength-item valid' : 'strength-item invalid';
        
        document.getElementById('req-lower').className = 
            requirements.hasLower ? 'strength-item valid' : 'strength-item invalid';
        
        document.getElementById('req-number').className = 
            requirements.hasNumber ? 'strength-item valid' : 'strength-item invalid';
        
        document.getElementById('req-special').className = 
            requirements.hasSpecial ? 'strength-item valid' : 'strength-item invalid';
    }
    
    // Determine if all requirements are met
    const isValid = requirements.minLength && 
                    requirements.hasUpper && 
                    requirements.hasLower && 
                    requirements.hasNumber && 
                    requirements.hasSpecial;
    
    return {
        isValid: isValid,
        requirements: requirements
    };
}

// ============================================

/**
 * Validates password on form submission
 * Checks both strength requirements and password confirmation
 * 
 * @param {string} password - Password field value
 * @param {string} confirm - Confirm password field value
 * @returns {boolean} - True if valid, false otherwise
 */
function validatePassword(password, confirm) {
    // Check password strength
    const result = checkPasswordStrength(password);
    
    if (!result.isValid) {
        alert(
            'Password does not meet the strength requirements:\n\n' +
            '• At least 8 characters\n' +
            '• At least 1 uppercase letter (A-Z)\n' +
            '• At least 1 lowercase letter (a-z)\n' +
            '• At least 1 number (0-9)\n' +
            '• At least 1 special character (!@#$%^&*)'
        );
        return false;
    }
    
    // Check if passwords match
    if (password !== confirm) {
        alert('Passwords do not match!');
        return false;
    }
    
    return true;
}

// ============================================

/**
 * Initializes real-time password validation on a form
 * Sets up event listeners for password field
 * Optionally disables submit button until password is valid
 * 
 * @param {string} passwordId - ID of password input field
 * @param {string} confirmId - ID of confirm password input field
 * @param {string} submitId - ID of submit button (optional)
 */
function initPasswordValidation(passwordId, confirmId, submitId = null) {
    // Get password field
    const passwordField = document.getElementById(passwordId);
    
    if (!passwordField) {
        console.warn('Password field not found:', passwordId);
        return;
    }
    
    // Add real-time validation on keyup
    passwordField.addEventListener('keyup', function() {
        checkPasswordStrength(this.value);
    });
    
    // Optionally disable submit button until password is valid
    if (submitId) {
        const submitBtn = document.getElementById(submitId);
        
        if (submitBtn) {
            // Initially disable submit button
            submitBtn.disabled = true;
            
            // Enable/disable based on password strength
            passwordField.addEventListener('keyup', function() {
                const result = checkPasswordStrength(this.value);
                submitBtn.disabled = !result.isValid;
            });
        }
    }
}

// ============================================

/**
 * Simple password match validation
 * Checks if password and confirm password fields match
 * 
 * @param {string} passwordId - ID of password field
 * @param {string} confirmId - ID of confirm password field
 * @returns {boolean} - True if passwords match
 */
function passwordsMatch(passwordId, confirmId) {
    const password = document.getElementById(passwordId).value;
    const confirm = document.getElementById(confirmId).value;
    
    if (password !== confirm) {
        alert('Passwords do not match!');
        return false;
    }
    
    return true;
}

// ============================================