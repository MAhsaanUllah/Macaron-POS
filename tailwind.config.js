import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                primary: 'rgb(var(--md-primary) / <alpha-value>)',
                'on-primary': 'rgb(var(--md-on-primary) / <alpha-value>)',
                background: 'rgb(var(--md-background) / <alpha-value>)',
                surface: 'rgb(var(--md-surface) / <alpha-value>)',
                'active-surface': 'rgb(var(--md-surface-container) / <alpha-value>)',
                outline: 'rgb(var(--md-outline) / <alpha-value>)',
                'on-surface': 'rgb(var(--md-on-surface) / <alpha-value>)',
                'on-surface-variant': 'rgb(var(--md-on-surface-variant) / <alpha-value>)',
                error: 'rgb(var(--md-error) / <alpha-value>)',
            },
            borderRadius: {
                DEFAULT: '0.5rem',
                lg: '0.75rem',
                xl: '1rem',
                '2xl': '1rem',
                full: '9999px',
            },
            spacing: {
                gutter: '16px',
                base: '8px',
                'touch-target': '48px',
                'btn-h': '56px',
            },
            fontFamily: {
                'body-lg': ['Hanken Grotesk', 'sans-serif'],
                'label-numeric': ['JetBrains Mono', 'monospace'],
                'display-lg': ['Hanken Grotesk', 'sans-serif'],
                'title-md': ['Hanken Grotesk', 'sans-serif'],
                'headline-lg-mobile': ['Hanken Grotesk', 'sans-serif'],
                'body-sm': ['Hanken Grotesk', 'sans-serif'],
                'headline-lg': ['Hanken Grotesk', 'sans-serif'],
            },
            fontSize: {
                'body-lg': ['1.125rem', { lineHeight: '1.6', fontWeight: '400' }],
                'label-numeric': ['1rem', { lineHeight: '1.0', letterSpacing: '0.05em', fontWeight: '500' }],
                'display-lg': ['3rem', { lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '700' }],
                'title-md': ['1.25rem', { lineHeight: '1.4', fontWeight: '600' }],
                'headline-lg-mobile': ['1.5rem', { lineHeight: '1.2', fontWeight: '600' }],
                'body-sm': ['0.875rem', { lineHeight: '1.5', fontWeight: '400' }],
                'headline-lg': ['2rem', { lineHeight: '1.2', fontWeight: '600' }],
            },
        },
    },
    plugins: [forms],
};
