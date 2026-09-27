/**
 * Auth Pages - OTP Handler & Theme Toggle
 */

(() => {
    'use strict'

    document.documentElement.classList.remove('auth-fade-out')
    window.addEventListener('pageshow', function(){ document.documentElement.classList.remove('auth-fade-out') })

    // ─── Theme Toggle (cycles: auto → light → dark) ───
    const themeToggle = document.getElementById('themeToggle')
    const cycleOrder = ['auto', 'light', 'dark']
    const iconMap = { 'auto': 'monitor', 'light': 'sun', 'dark': 'moon' }

    const applyTheme = (mode) => {
        let isDark = false
        if (mode === 'auto') {
            isDark = window.matchMedia('(prefers-color-scheme: dark)').matches
        } else {
            isDark = mode === 'dark'
        }
        document.documentElement.classList.toggle('dark', isDark)
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light')
    }

    const updateThemeIcon = (mode) => {
        var iconName = iconMap[mode] || 'moon'
        document.querySelectorAll('.theme-icon').forEach(function(el) {
            el.classList.toggle('hidden', !el.classList.contains('theme-icon-' + iconName))
        })
    }

    if (themeToggle) {
        const syncTheme = () => {
            const stored = localStorage.getItem('theme') || 'auto'
            applyTheme(stored)
            updateThemeIcon(stored)
        }

        syncTheme()

        themeToggle.addEventListener('click', () => {
            const current = localStorage.getItem('theme') || 'auto'
            const idx = cycleOrder.indexOf(current)
            const next = cycleOrder[(idx + 1) % cycleOrder.length]
            localStorage.setItem('theme', next)

            var icon = document.getElementById('themeIcon')
            if (icon) {
                icon.style.transform = 'rotate(360deg) scale(0.7)'
                icon.style.opacity = '0'
            }

            setTimeout(function() {
                applyTheme(next)
                updateThemeIcon(next)
                if (icon) {
                    icon.style.transform = 'rotate(0deg) scale(1)'
                    icon.style.opacity = '1'
                }
            }, 120)
        })

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            const stored = localStorage.getItem('theme')
            if (!stored || stored === 'auto') {
                applyTheme('auto')
                updateThemeIcon('auto')
            }
        })
    }

    // ─── OTP Inputs ───
    const otpInputs = document.querySelectorAll('.otp-input')
    const otpHidden = document.getElementById('otp-hidden')

    if (otpInputs.length > 0 && otpHidden) {
        const updateHidden = () => {
            const code = Array.from(otpInputs).map(input => input.value).join('')
            otpHidden.value = code
        }

        otpInputs.forEach((input, index) => {
            if (index === 0) {
                setTimeout(() => input.focus(), 100)
            }

            input.addEventListener('input', (e) => {
                const value = e.target.value.replace(/\D/g, '')
                e.target.value = value

                if (value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus()
                }

                updateHidden()

                const allFilled = Array.from(otpInputs).every(i => i.value.length === 1)
                if (allFilled) {
                    const form = e.target.closest('form')
                    if (form) {
                        const card = form.closest('.auth-card')
                        const ls = card?.querySelector('.auth-loading-state')
                        if (ls) {
                            ls.classList.remove('hidden')
                            ls.style.removeProperty('display')
                            card.classList.add('loading')
                            setTimeout(() => form.submit(), 1500)
                        } else {
                            setTimeout(() => form.submit(), 200)
                        }
                    }
                }
            })

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus()
                }
            })

            input.addEventListener('paste', (e) => {
                e.preventDefault()
                const pasteData = (e.clipboardData || window.clipboardData).getData('text')
                const numbers = pasteData.replace(/\D/g, '').slice(0, 6)

                numbers.split('').forEach((char, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = char
                    }
                })

                const nextIndex = Math.min(numbers.length, otpInputs.length - 1)
                otpInputs[nextIndex]?.focus()

                updateHidden()

                if (numbers.length === 6) {
                    const form = otpInputs[0].closest('form')
                    if (form) {
                        const card = form.closest('.auth-card')
                        const ls = card?.querySelector('.auth-loading-state')
                        if (ls) {
                            ls.classList.remove('hidden')
                            ls.style.removeProperty('display')
                            card.classList.add('loading')
                            setTimeout(() => form.submit(), 1500)
                        } else {
                            setTimeout(() => form.submit(), 200)
                        }
                    }
                }
            })

            input.addEventListener('keypress', (e) => {
                if (!/\d/.test(e.key)) {
                    e.preventDefault()
                }
            })
        })
    }

    // ─── Password Toggle (eye blink animation) ───
    var passwordToggle = document.getElementById('passwordToggle')

    if (passwordToggle) {
        passwordToggle.addEventListener('click', function() {
            var container = this.closest('.relative')
            if (!container) return
            var input = container.querySelector('input')
            if (!input) return

            var isPassword = input.type === 'password'
            input.type = isPassword ? 'text' : 'password'

            var eyeIcon = document.getElementById('eyeIcon')
            if (!eyeIcon) return

            // Blink: scaleY 0 (close), swap icon, scaleY 1 (open)
            eyeIcon.style.transform = 'scaleY(0)'
            eyeIcon.style.opacity = '0'

            setTimeout(function() {
                var openIcon = document.querySelector('.eye-icon-open')
                var closedIcon = document.querySelector('.eye-icon-closed')
                if (openIcon && closedIcon) {
                    if (isPassword) {
                        // was hidden → now visible → show open eye
                        closedIcon.classList.add('hidden')
                        openIcon.classList.remove('hidden')
                    } else {
                        // was visible → now hidden → show closed eye
                        openIcon.classList.add('hidden')
                        closedIcon.classList.remove('hidden')
                    }
                }
                eyeIcon.style.transform = 'scaleY(1)'
                eyeIcon.style.opacity = '1'
            }, 130)
        })
    }

    // ─── Card Morph Loading ───
    const authForms = document.querySelectorAll('.auth-form')
    console.log('[AUTH] forms found:', authForms.length)

    authForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            console.log('[AUTH] submit intercepted')
            const submitBtn = form.querySelector('button[type="submit"]')
            if (!submitBtn || submitBtn.disabled) return

            submitBtn.disabled = true

            const card = form.closest('.auth-card')
            const loadingState = card?.querySelector('.auth-loading-state')
            console.log('[AUTH] card:', !!card, 'loadingState:', !!loadingState)

            if (card && loadingState) {
                e.preventDefault()
                loadingState.classList.remove('hidden')
                loadingState.style.removeProperty('display')
                card.classList.add('loading')
                console.log('[AUTH] loading animation started')

                setTimeout(() => {
                    document.documentElement.classList.add('auth-fade-out')
                    console.log('[AUTH] fade-out then submit')
                    setTimeout(() => {
                        form.submit()
                    }, 200)
                }, 1500)
            } else {
                submitBtn.style.opacity = '0.6'
                submitBtn.style.cursor = 'not-allowed'
            }
        })
    })

    // ─── Alert Auto-Dismiss ───
    const alerts = document.querySelectorAll('.auth-alert')

    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease'
            alert.style.opacity = '0'
            setTimeout(() => alert.remove(), 300)
        }, 5000)
    })

})()
