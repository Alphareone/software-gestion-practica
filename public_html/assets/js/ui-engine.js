(() => {
    'use strict'
    const getPreferredTheme = () => {
        const storedTheme = localStorage.getItem('theme')
        return storedTheme ? storedTheme : 'auto'
    }

    const setTheme = theme => {
        let resolved
        if (theme === 'auto') {
            const prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches
            resolved = prefersLight ? 'light' : 'dark'
        } else {
            resolved = theme
        }
        document.documentElement.setAttribute('data-theme', resolved)
        document.documentElement.classList.toggle('dark', resolved === 'dark')
        if (window.refreshDashboardCharts) window.refreshDashboardCharts()
    }

    // initialize
    const initial = getPreferredTheme()
    setTheme(initial)

    // expose manager for other scripts
    window.themeManager = {
        get: () => localStorage.getItem('theme') || 'auto',
        set: (t) => { localStorage.setItem('theme', t); setTheme(t); }
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        const storedTheme = localStorage.getItem('theme')
        if (!storedTheme || storedTheme === 'auto') {
            setTheme('auto')
        }
    })

    // User Profile Dropdown
    const userProfileBtn = document.getElementById('userProfileBtn')
    const userDropdownMenu = document.getElementById('userDropdownMenu')
    
    if (userProfileBtn && userDropdownMenu) {
        userProfileBtn.addEventListener('click', (e) => {
            e.stopPropagation()
            userDropdownMenu.classList.toggle('show')
            userProfileBtn.classList.toggle('active')
            // Close sync widget panel if open
            var syncPanel = document.getElementById('syncPanel')
            if (syncPanel) syncPanel.style.display = 'none'
        })
        
        document.addEventListener('click', (e) => {
            if (!userProfileBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                userDropdownMenu.classList.remove('show')
                userProfileBtn.classList.remove('active')
            }
        })
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                userDropdownMenu.classList.remove('show')
                userProfileBtn.classList.remove('active')
            }
        })
        
        // Theme options in user dropdown
        const updateThemeOptions = () => {
            const currentTheme = localStorage.getItem('theme') || 'auto'
            document.querySelectorAll('.theme-option').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.theme === currentTheme)
            })
        }

        document.querySelectorAll('.theme-option').forEach(btn => {
            btn.addEventListener('click', () => {
                window.themeManager.set(btn.dataset.theme)
                updateThemeOptions()
                // Close dropdown after selection
                userDropdownMenu.classList.remove('show')
                userProfileBtn.classList.remove('active')
            })
        })

        updateThemeOptions()
        }
})()