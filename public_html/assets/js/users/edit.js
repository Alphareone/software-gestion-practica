/**
 * User Edit Form - Real-time Validation & Password Generator
 */

(function() {
  'use strict';

  // Elements
  const form = document.getElementById('userEditForm');
  const passwordInput = document.getElementById('password');
  const confirmInput = document.getElementById('confirm_password');
  const togglePassword = document.getElementById('togglePassword');
  const toggleConfirm = document.getElementById('toggleConfirmPassword');
  const generateBtn = document.getElementById('generatePassword');
  const copyBtn = document.getElementById('copyPassword');
  const strengthBar = document.querySelector('.ue-strength-fill');
  const strengthText = document.querySelector('.ue-strength-text');
  const requirements = document.getElementById('passwordRequirements');
  const matchStatus = document.getElementById('passwordMatch');
  const emailInput = document.getElementById('email');
  const emailError = document.getElementById('emailError');

  // Danger Zone buttons
  const btnResetPassword = document.getElementById('btnResetPassword');
  const btnForceLogout = document.getElementById('btnForceLogout');
  const btnDeleteUser = document.getElementById('btnDeleteUser');

  // Modal
  const modal = document.getElementById('confirmModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalMessage = document.getElementById('modalMessage');
  const modalCancel = document.getElementById('modalCancel');
  const modalConfirm = document.getElementById('modalConfirm');

  let currentModalAction = null;

  // ───────────────────────────────────────────────
  // Password Generator
  // ───────────────────────────────────────────────

  function generatePassword(length = 12) {
    const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lowercase = 'abcdefghijklmnopqrstuvwxyz';
    const numbers = '0123456789';
    const symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    let password = '';
    
    // Asegurar al menos uno de cada
    password += uppercase[Math.floor(Math.random() * uppercase.length)];
    password += lowercase[Math.floor(Math.random() * lowercase.length)];
    password += numbers[Math.floor(Math.random() * numbers.length)];
    password += symbols[Math.floor(Math.random() * symbols.length)];
    
    // Rellenar el resto
    const allChars = uppercase + lowercase + numbers + symbols;
    for (let i = password.length; i < length; i++) {
      password += allChars[Math.floor(Math.random() * allChars.length)];
    }
    
    // Mezclar
    return password.split('').sort(() => Math.random() - 0.5).join('');
  }

  function showPassword(input, toggleBtn) {
    input.type = 'text';
    toggleBtn.querySelector('.eye-open').style.display = 'none';
    toggleBtn.querySelector('.eye-closed').style.display = 'block';
  }

  function hidePassword(input, toggleBtn) {
    input.type = 'password';
    toggleBtn.querySelector('.eye-open').style.display = 'block';
    toggleBtn.querySelector('.eye-closed').style.display = 'none';
  }

  // ───────────────────────────────────────────────
  // Password Strength Calculator
  // ───────────────────────────────────────────────

  function calculateStrength(password) {
    let score = 0;
    const reqs = {
      length: password.length >= 8,
      uppercase: /[A-Z]/.test(password),
      lowercase: /[a-z]/.test(password),
      number: /[0-9]/.test(password),
      symbol: /[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password)
    };

    // Count met requirements
    Object.values(reqs).forEach(met => { if (met) score++; });

    // Bonus for length
    if (password.length >= 12) score += 1;
    if (password.length >= 16) score += 1;

    return { score: Math.min(score, 5), reqs };
  }

  function updateStrengthUI(password) {
    const { score, reqs } = calculateStrength(password);
    
    // Update bar
    strengthBar.setAttribute('data-strength', score);
    
    // Update text
    const texts = ['Sin contraseña', 'Muy débil', 'Débil', 'Media', 'Fuerte', 'Muy fuerte'];
    strengthText.textContent = password ? texts[score] : 'Sin contraseña';
    
    // Update requirements
    Object.entries(reqs).forEach(([req, met]) => {
      const li = requirements.querySelector(`[data-req="${req}"]`);
      if (li) {
        li.classList.toggle('met', met);
        li.querySelector('.ue-req-icon').textContent = met ? '✓' : '○';
      }
    });
  }

  // ───────────────────────────────────────────────
  // Password Match Validation
  // ───────────────────────────────────────────────

  function checkPasswordMatch() {
    const pwd = passwordInput.value;
    const confirm = confirmInput.value;

    if (!confirm) {
      matchStatus.textContent = '';
      matchStatus.className = 'ue-match-status';
      return;
    }

    if (pwd === confirm) {
      matchStatus.textContent = '✓ Las contraseñas coinciden';
      matchStatus.className = 'ue-match-status match';
    } else {
      matchStatus.textContent = '✗ Las contraseñas no coinciden';
      matchStatus.className = 'ue-match-status mismatch';
    }
  }

  // ───────────────────────────────────────────────
  // Email Validation
  // ───────────────────────────────────────────────

  function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }

  function onEmailBlur() {
    const email = emailInput.value.trim();
    if (email && !validateEmail(email)) {
      emailError.textContent = 'Email inválido';
      emailInput.style.borderColor = '#dc2626';
    } else {
      emailError.textContent = '';
      emailInput.style.borderColor = '';
    }
  }

  // ───────────────────────────────────────────────
  // Copy to Clipboard
  // ───────────────────────────────────────────────

  async function copyPasswordToClipboard() {
    const password = passwordInput.value;
    if (!password) return;

    try {
      await navigator.clipboard.writeText(password);
      showToast('Contraseña copiada al portapapeles', 'success');
      
      // Visual feedback
      copyBtn.style.background = 'rgba(22, 163, 74, 0.1)';
      copyBtn.style.color = '#16a34a';
      setTimeout(() => {
        copyBtn.style.background = '';
        copyBtn.style.color = '';
      }, 500);
    } catch (err) {
      showToast('No se pudo copiar la contraseña', 'error');
    }
  }

  // ───────────────────────────────────────────────
  // Toast Notification
  // ───────────────────────────────────────────────

  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: ${type === 'success' ? '#16a34a' : type === 'error' ? '#dc2626' : '#3b82f6'};
      color: white;
      padding: 12px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 9999;
      animation: slideInRight 0.3s ease-out;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.style.animation = 'slideOutRight 0.3s ease-out';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // ───────────────────────────────────────────────
  // Modal Handlers
  // ───────────────────────────────────────────────

  function showModal(title, message, action) {
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    currentModalAction = action;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function hideModal() {
    modal.style.display = 'none';
    document.body.style.overflow = '';
    currentModalAction = null;
  }

  function handleDangerAction(action) {
    if (action === 'logout') {
      showModal(
        '¿Forzar logout?',
        `Se cerrarán todas las sesiones activas de este usuario. Deberá volver a iniciar sesión.`,
        async () => {
          try {
            const response = await fetch(`${URLROOT}/users/forceLogout/${USER_ID}`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                csrf_token: document.querySelector('input[name="csrf_token"]').value
              })
            });
            
            const result = await response.json();
            if (result.success) {
              showToast('Sesión cerrada exitosamente', 'success');
            } else {
              showToast('Error: ' + (result.error || 'Falló'), 'error');
            }
          } catch (err) {
            showToast('Error de conexión', 'error');
          }
          hideModal();
        }
      );
    }
  }

  // ───────────────────────────────────────────────
  // Event Listeners
  // ───────────────────────────────────────────────

  // Toggle password visibility
  if (togglePassword) {
    togglePassword.addEventListener('click', () => {
      if (passwordInput.type === 'password') {
        showPassword(passwordInput, togglePassword);
      } else {
        hidePassword(passwordInput, togglePassword);
      }
    });
  }

  if (toggleConfirm) {
    toggleConfirm.addEventListener('click', () => {
      if (confirmInput.type === 'password') {
        showPassword(confirmInput, toggleConfirm);
      } else {
        hidePassword(confirmInput, toggleConfirm);
      }
    });
  }

  // Generate password
  if (generateBtn) {
    generateBtn.addEventListener('click', () => {
      const newPassword = generatePassword(12);
      passwordInput.value = newPassword;
      confirmInput.value = '';
      updateStrengthUI(newPassword);
      checkPasswordMatch();
      copyBtn.style.display = 'flex';
      showToast('Contraseña generada', 'success');
    });
  }

  // Copy password
  if (copyBtn) {
    copyBtn.addEventListener('click', copyPasswordToClipboard);
  }

  // Password input - real-time validation
  if (passwordInput) {
    passwordInput.addEventListener('input', () => {
      updateStrengthUI(passwordInput.value);
      checkPasswordMatch();
    });
  }

  // Confirm password input - real-time match check
  if (confirmInput) {
    confirmInput.addEventListener('input', checkPasswordMatch);
  }

  // Email validation
  if (emailInput) {
    emailInput.addEventListener('blur', onEmailBlur);
  }

  // Danger zone buttons
  if (btnForceLogout) {
    btnForceLogout.addEventListener('click', () => handleDangerAction('logout'));
  }

  // Modal buttons
  if (modalCancel) {
    modalCancel.addEventListener('click', hideModal);
  }
  if (modalConfirm) {
    modalConfirm.addEventListener('click', () => {
      if (currentModalAction) {
        currentModalAction();
      }
    });
  }

  // Close modal on backdrop click
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        hideModal();
      }
    });
  }

  // Close modal on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
      hideModal();
    }
  });

  // Form submit - keyboard shortcut (Ctrl/Cmd + S)
  if (form) {
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        form.dispatchEvent(new Event('submit'));
      }
    });

    form.addEventListener('submit', (e) => {
      const pwd = passwordInput.value;
      const confirm = confirmInput.value;

      // Validate passwords match
      if (pwd && confirm && pwd !== confirm) {
        e.preventDefault();
        showToast('Las contraseñas no coinciden', 'error');
        confirmInput.focus();
        return;
      }

      // Validate password requirements if password is set
      if (pwd) {
        const { score } = calculateStrength(pwd);
        if (score < 3) {
          e.preventDefault();
          showToast('La contraseña es muy débil', 'error');
          passwordInput.focus();
          return;
        }
      }

      // Show saving state
      const submitBtn = document.getElementById('btnSave');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
          <svg class="spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
            <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="1">
              <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
            </path>
          </svg>
          Guardando...
        `;
      }
    });
  }

  // Initialize strength UI if password exists
  if (passwordInput && passwordInput.value) {
    updateStrengthUI(passwordInput.value);
  }

})();
