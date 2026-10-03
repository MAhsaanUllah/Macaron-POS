<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- View-transition opt-in must be inline: Chromium evaluates it before external CSS arrives --}}
    <style>@view-transition { navigation: auto; }</style>

    <title>{{ config('app.name', 'Macaron') }} Sales Counter</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-dark.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <script>
        const savedTheme = localStorage.getItem('macaron-theme');
        document.documentElement.classList.toggle('dark', savedTheme ? savedTheme === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);
        // Paint the sidebar state before first frame so navigation never shows it pop in
        document.documentElement.classList.toggle('nav-collapsed', localStorage.getItem('macaron-sidebar') === 'closed');

        function toggleTheme() {
            const dark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('macaron-theme', dark ? 'dark' : 'light');
            document.getElementById('theme-icon')?.replaceChildren(document.createTextNode(dark ? 'light_mode' : 'dark_mode'));
        }

        document.addEventListener('DOMContentLoaded', () => {
            const icon = document.getElementById('theme-icon');
            if (icon) icon.textContent = document.documentElement.classList.contains('dark') ? 'light_mode' : 'dark_mode';
        });
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-on-surface font-body-lg antialiased overflow-hidden h-screen flex">
    <div x-data="posEngine()" @keydown.window="handleScannerKey($event)" class="flex-1 flex overflow-hidden">
    <!-- Sidebar Navigation -->
    <aside class="app-sidebar hidden lg:flex flex-col p-4 bg-surface border-r border-outline w-64 flex-shrink-0 z-50">
        <div class="flex items-center gap-3 p-4 border-b border-outline mb-6">
            @if(isset($settings) && $settings->logo_path)
                <img src="{{ asset('storage/' . $settings->logo_path) }}" alt="Logo" class="h-10 w-10 object-contain rounded-lg">
            @else
                <div class="h-10 w-10 rounded-lg bg-primary/20 flex items-center justify-center text-primary font-bold text-xl">
                    {{ substr($settings->shop_name ?? 'S', 0, 1) }}
                </div>
            @endif
            
            <div class="min-w-0">
                <h1 class="text-sm font-bold text-white tracking-wide truncate">{{ $settings->shop_name ?? 'Macaron' }}</h1>
                <p class="text-[10px] text-on-surface-variant uppercase tracking-widest">MITHAI & BAKERY • SALES COUNTER 01</p>
            </div>
        </div>
        <nav class="flex-1 space-y-1">
            <a title="Start a new customer bill" class="flex items-center gap-3 p-3.5 {{ request()->routeIs('dashboard') ? 'active-nav' : 'text-on-surface-variant hover:bg-active-surface' }} font-bold rounded-xl transition-all group" href="{{ route('dashboard') }}">
                <span class="material-symbols-rounded text-xl group-hover:scale-110 transition-transform">point_of_sale</span>
                <span class="text-xs uppercase tracking-wider">New Sale</span>
            </a>
            @if(auth()->user()?->hasAnyRole(['admin', 'manager']))
            <a title="Manage sweets, boxes and other charges" class="flex items-center gap-3 p-3.5 {{ request()->routeIs('menu.index') ? 'active-nav' : 'text-on-surface-variant hover:bg-active-surface' }} transition-all rounded-xl group" href="{{ route('menu.index') }}">
                <span class="material-symbols-rounded text-xl group-hover:scale-110 transition-transform">inventory_2</span>
                <span class="text-xs uppercase tracking-wider">Products & Add-ons</span>
            </a>
            @endif
            <a title="Find receipts and review completed sales" class="flex items-center gap-3 p-3.5 {{ request()->routeIs('orders.index') ? 'active-nav' : 'text-on-surface-variant hover:bg-active-surface' }} transition-all rounded-xl group" href="{{ route('orders.index') }}">
                <span class="material-symbols-rounded text-xl group-hover:scale-110 transition-transform">receipt_long</span>
                <span class="text-xs uppercase tracking-wider">Sales History</span>
            </a>
            @if(auth()->user()?->hasAnyRole(['admin', 'manager']))
            <a title="Record daily mithai production and see how fast each batch sells" class="flex items-center gap-3 p-3.5 {{ request()->routeIs('batches.*') ? 'active-nav' : 'text-on-surface-variant hover:bg-active-surface' }} transition-all rounded-xl group" href="{{ route('batches.index') }}">
                <span class="material-symbols-rounded text-xl group-hover:scale-110 transition-transform">inventory</span>
                <span class="text-xs uppercase tracking-wider">Daily Batches</span>
            </a>
            @endif
            @if(auth()->user()?->hasAnyRole(['admin', 'manager']))
            <a title="Review sales, costs and shift performance" class="flex items-center gap-3 p-3.5 {{ request()->routeIs('reports.index') ? 'active-nav' : 'text-on-surface-variant hover:bg-active-surface' }} transition-all rounded-xl group" href="{{ route('reports.index') }}">
                <span class="material-symbols-rounded text-xl group-hover:scale-110 transition-transform">analytics</span>
                <span class="text-xs uppercase tracking-wider">Business Reports</span>
            </a>
            @endif
            @if(auth()->user()?->isAdmin())
            <a title="Configure shop, payments, hardware and staff" class="flex items-center gap-3 p-3.5 {{ request()->routeIs('settings.index') ? 'active-nav' : 'text-on-surface-variant hover:bg-active-surface' }} transition-all rounded-xl group" href="{{ route('settings.index') }}">
                <span class="material-symbols-rounded text-xl group-hover:scale-110 transition-transform">settings</span>
                <span class="text-xs uppercase tracking-wider">Shop Settings</span>
            </a>
            @endif
            @php
                $hasActiveShift = \App\Models\Shift::where('user_id', auth()->id())->where('status', 'open')->exists();
            @endphp
            @if(! $hasActiveShift)
                <a class="flex items-center gap-3 p-3.5 {{ request()->routeIs('shift.open') ? 'active-nav' : 'text-primary hover:bg-primary/10' }} transition-all rounded-xl group" href="{{ route('shift.open') }}">
                    <span class="material-symbols-rounded text-xl group-hover:scale-110 transition-transform">lock_open</span>
                    <span class="text-xs uppercase tracking-wider">Open Register</span>
                </a>
            @endif
        </nav>
        <div class="mt-auto pt-4 border-t border-outline">
            <div class="flex items-center gap-3 p-2 mb-3 bg-active-surface/30 rounded-xl border border-transparent">
                <div class="w-8 h-8 rounded-lg bg-primary/20 border border-primary/20 flex items-center justify-center overflow-hidden font-black text-xs text-primary">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'S', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-bold truncate uppercase tracking-tighter">{{ auth()->user()?->name ?? 'Staff User' }}</p>
                </div>
            </div>
            @if($hasActiveShift)
                <a href="{{ route('shift.close') }}" title="Count cash, print the Z-report, and end this register shift" class="w-full h-10 rounded-xl border border-error/30 text-error text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2 hover:bg-error hover:text-on-primary transition-all active:scale-95">
                    <span class="material-symbols-rounded text-sm">point_of_sale</span>Close Register
                </a>
            @else
                <form action="{{ route('logout') }}" method="POST">@csrf<button class="w-full h-10 rounded-xl border border-outline text-on-surface-variant text-[10px] font-black uppercase tracking-widest">Log Out</button></form>
            @endif
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col min-w-0">
        <!-- TopAppBar -->
        <header class="app-topbar flex justify-between items-center w-full px-6 h-14 bg-surface border-b border-outline flex-shrink-0 z-40 transition-all">
            <div class="flex items-center gap-6">
                <button type="button" @click="toggleSidebar()" class="hidden lg:flex w-10 h-10 rounded-full hover:bg-active-surface transition-colors items-center justify-center border border-outline" :aria-label="sidebarOpen ? 'Hide navigation' : 'Show navigation'" :title="sidebarOpen ? 'Hide navigation' : 'Show navigation'">
                    <span class="material-symbols-rounded text-on-surface text-base" x-text="sidebarOpen ? 'left_panel_close' : 'left_panel_open'"></span>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
                    <span class="font-bold text-[10px] tracking-[0.2em] uppercase">Counter Ready</span>
                </div>
                
                <div class="flex items-center gap-2 px-3 py-1.5 bg-background border border-outline rounded-xl text-xs" title="Sales are stored on this counter computer. Use Shop Settings to download a backup.">
                    <span class="material-symbols-rounded text-sm text-primary">storage</span>
                    <span class="text-[9px] font-black uppercase tracking-wider text-primary">Local Mode</span>
                </div>
            </div>

            <div class="flex-1 max-w-lg mx-8">
                <div class="relative group">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-rounded text-on-surface-variant text-base">search</span>
                    <input x-model="searchQuery" class="w-full bg-background border border-outline rounded-xl h-10 pl-11 pr-4 text-on-surface focus:ring-1 focus:ring-primary/50 text-xs transition-all placeholder:text-on-surface-variant/30" placeholder="Search sweets, cakes or barcode..." type="text"/>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden sm:flex flex-col items-end pr-4 border-r border-outline">
                    <span class="text-[9px] font-black text-on-surface-variant uppercase tracking-widest">Time</span>
                    <span class="font-label-numeric text-xs font-black text-primary" x-text="currentTime"></span>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="toggleTheme()" class="w-10 h-10 rounded-full hover:bg-active-surface transition-colors flex items-center justify-center border border-outline" title="Switch light or dark theme" aria-label="Switch light or dark theme">
                        <span id="theme-icon" class="material-symbols-rounded text-on-surface text-base">dark_mode</span>
                    </button>
                    <button class="w-10 h-10 rounded-xl hover:bg-active-surface transition-all flex items-center justify-center relative border border-outline group">
                        <span class="material-symbols-rounded text-on-surface text-base">notifications</span>
                        <div class="absolute top-2.5 right-2.5 w-1.5 h-1.5 bg-error rounded-full border border-surface"></div>
                    </button>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="hidden md:flex px-4 h-10 bg-active-surface text-on-surface font-black text-[10px] uppercase tracking-widest rounded-xl hover:bg-outline transition-all items-center gap-2 border border-outline">
                        <span class="material-symbols-rounded text-sm">lock</span>
                        Lock
                    </a>
                </div>
            </div>
        </header>

        @yield('content')
    </main>

    <!-- Thermal Receipt Template (Hidden) -->
    <div id="thermal-receipt" class="hidden print:block bg-white text-black p-1 w-[76mm] font-mono text-[12px] leading-relaxed antialiased selection:bg-transparent">
        <style type="text/css" media="print">
            @page { size: 76mm auto; margin: 0mm; }
            body { background: white; color: black; margin: 0; padding: 0; font-family: 'Courier New', monospace; }
            #thermal-receipt { display: block !important; width: 76mm; padding: 3mm; box-sizing: border-box; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .flex { display: flex; }
            .grid { display: grid; }
            .grid-cols-12 { grid-template-columns: repeat(12, minmax(0, 1fr)); }
            .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .col-span-2 { grid-column: span 2 / span 2; }
            .col-span-3 { grid-column: span 3 / span 3; }
            .col-span-4 { grid-column: span 4 / span 4; }
            .col-span-7 { grid-column: span 7 / span 7; }
            .col-span-8 { grid-column: span 8 / span 8; }
            .gap-y-0\.5 { row-gap: 2px; }
            .font-black { font-weight: 900; }
            .font-bold { font-weight: 700; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .text-gray-600 { color: #4b5563; }
            .text-gray-500 { color: #6b7280; }
            .text-gray-400 { color: #9ca3af; }
            .text-rose-700 { color: #be123c; }
            .bg-black { background-color: #000; }
            .text-white { color: #fff; }
            .uppercase { text-transform: uppercase; }
            .border { border-width: 1px; border-style: solid; }
            .border-2 { border-width: 2px; border-style: solid; }
            .border-b { border-bottom-width: 1px; border-style: solid; }
            .border-t { border-top-width: 1px; border-style: solid; }
            .border-dashed { border-style: dashed; }
            .border-black { border-color: #000; }
            .border-gray-200 { border-color: #e5e7eb; }
            .p-1 { padding: 4px; }
            .p-2 { padding: 8px; }
            .px-2 { padding-left: 8px; padding-right: 8px; }
            .px-1 { padding-left: 4px; padding-right: 4px; }
            .py-0\.5 { padding-top: 2px; padding-bottom: 2px; }
            .py-1 { padding-top: 4px; padding-bottom: 4px; }
            .pb-1 { padding-bottom: 4px; }
            .pb-2 { padding-bottom: 8px; }
            .pb-3 { padding-bottom: 12px; }
            .pt-0\.5 { padding-top: 2px; }
            .pt-1 { padding-top: 4px; }
            .pt-2 { padding-top: 8px; }
            .mb-1 { margin-bottom: 4px; }
            .mb-2 { margin-bottom: 8px; }
            .mb-3 { margin-bottom: 12px; }
            .mb-4 { margin-bottom: 16px; }
            .mt-2 { margin-top: 8px; }
            .text-\[8px\] { font-size: 8px; }
            .text-\[9px\] { font-size: 9px; }
            .text-\[10px\] { font-size: 10px; }
            .text-\[11px\] { font-size: 11px; }
            .text-\[12px\] { font-size: 12px; }
            .text-lg { font-size: 18px; }
            .tracking-tight { letter-spacing: -0.025em; }
            .tracking-tighter { letter-spacing: -0.05em; }
            .tracking-wide { letter-spacing: 0.025em; }
            .tracking-wider { letter-spacing: 0.05em; }
            .tracking-widest { letter-spacing: 0.1em; }
            .leading-relaxed { line-height: 1.625; }
            .break-words { word-break: break-word; }
            .rounded-sm { border-radius: 2px; }
            .antialiased { -webkit-font-smoothing: antialiased; }
            .items-start { align-items: flex-start; }
            .items-center { align-items: center; }
            .justify-between { justify-content: space-between; }
            .hidden { display: none; }
            .space-y-0\.5 > * + * { margin-top: 2px; }
            .space-y-1 > * + * { margin-top: 4px; }
            .space-y-1\.5 > * + * { margin-top: 6px; }
        </style>

        <div class="text-center pt-1 pb-3 mb-2 border-b border-black">
            @if($settings->logo_path)
                <img src="{{ asset('storage/' . $settings->logo_path) }}" alt="" style="height:44px;width:auto;margin:0 auto 4px;display:block">
            @endif
            <h2 class="text-xl font-black tracking-tighter uppercase mb-0.5">{{ $settings->shop_name ?? 'Macaron' }}</h2>
            @if($settings->address)
                <p class="text-[11px] font-bold tracking-tight">{{ $settings->address }}</p>
            @endif
            @if($settings->phone_number)
                <p class="text-[11px]">Ph: {{ $settings->phone_number }}</p>
            @endif
            @if($settings->tax_number)
                <p class="text-[10px] uppercase tracking-wide font-bold">NTN/STRN: {{ $settings->tax_number }}</p>
            @endif
        </div>

        <div id="receipt-void-band" class="text-center text-[12px] font-black tracking-widest border-2 border-black py-1 mb-2 mt-2" style="display:none">
            *** CANCELLED / REFUNDED — VOID ***
        </div>

        <div id="receipt-meta" class="grid grid-cols-2 gap-y-0.5 text-[11px] pt-0.5 pb-3 mb-2 border-b border-dashed border-black">
            <div><span class="text-gray-600">TOKEN:</span> <span id="receipt-token" class="font-black">#0000</span></div>
            <div class="text-right"><span class="text-gray-600">MODE:</span> <span id="receipt-type" class="font-bold uppercase">COUNTER</span></div>
            <div><span class="text-gray-600">DATE:</span> <span id="receipt-date" class="font-bold">{{ date('d-M-Y') }}</span></div>
            <div class="text-right"><span class="text-gray-600">TIME:</span> <span id="receipt-time" class="font-bold">{{ date('h:i A') }}</span></div>
            <div id="receipt-customer-row" class="px-1" style="display:none"><span class="text-gray-600">CUST:</span> <span id="receipt-customer-name" class="font-bold uppercase">-</span></div>
            <div id="receipt-phone-row" class="text-right px-1" style="display:none"><span class="text-gray-600">PHONE:</span> <span id="receipt-customer-phone" class="font-bold">-</span></div>

        </div>

        <div class="grid grid-cols-12 font-black text-[11px] border-b-2 border-black pb-2 mb-1">
            <div class="col-span-7">DESCRIPTION</div>
            <div class="col-span-2 text-center">QTY</div>
            <div class="col-span-3 text-right">PRICE</div>
        </div>

        <div id="receipt-items" class="space-y-1.5 pt-0.5 pb-3 mb-1 border-b border-dashed border-black">
            <!-- Dynamic Items -->
        </div>

        @php $taxPct = (float) ($config->tax_rate ?? 0); @endphp
        <div id="receipt-totals" class="space-y-1 text-[11px] font-bold pt-0.5 pb-3 mb-2 border-b border-black">
            <div class="grid grid-cols-12">
                <div class="col-span-8 text-right text-gray-600">SUBTOTAL:</div>
                <div class="col-span-4 text-right" id="receipt-subtotal">0.00</div>
            </div>
            <div class="grid grid-cols-12">
                <div class="col-span-8 text-right text-gray-600">SALES TAX{{ $taxPct > 0 ? ' ('.$taxPct.'%)' : '' }}:</div>
                <div class="col-span-4 text-right" id="receipt-tax">0.00</div>
            </div>
            <div id="receipt-discount-row" class="grid grid-cols-12" style="display:none">
                <div class="col-span-8 text-right text-gray-600">DISCOUNT:</div>
                <div class="col-span-4 text-right" id="receipt-discount">0.00</div>
            </div>
            <div id="receipt-points-row" class="grid grid-cols-12" style="display:none">
                <div class="col-span-8 text-right text-gray-600">POINTS REDEEMED:</div>
                <div class="col-span-4 text-right" id="receipt-points">0.00</div>
            </div>
        </div>

        <div class="flex justify-between items-center bg-black text-white p-2 mb-3 rounded-sm">
            <span class="text-[12px] font-black tracking-wider">NET PAYABLE:</span>
            <span id="receipt-total" class="text-lg font-black tracking-tight">{{ $settings->currency_symbol ?? 'Rs.' }} 0.00</span>
        </div>

        <div id="receipt-payment-row" class="text-center text-[10px] uppercase font-bold tracking-widest mb-4 border border-black py-1 rounded-sm">
            PAYMENT MODE: <span id="receipt-payment-method">CASH</span>
        </div>

        <div id="receipt-cash-block" class="space-y-1 text-[11px] font-bold pb-3 mb-2 border-b border-dashed border-black" style="display:none">
            <div class="grid grid-cols-12">
                <div class="col-span-8 text-right text-gray-600">CASH RECEIVED:</div>
                <div class="col-span-4 text-right" id="receipt-cash-received">0.00</div>
            </div>
            <div class="grid grid-cols-12">
                <div class="col-span-8 text-right text-gray-600">CHANGE:</div>
                <div class="col-span-4 text-right" id="receipt-change">0.00</div>
            </div>
        </div>

        <div id="receipt-ref-row" class="text-center text-[10px] uppercase font-bold tracking-widest mb-4 border border-black py-1 rounded-sm" style="display:none">
            REF: <span id="receipt-ref">-</span>
        </div>

        <div id="receipt-fbr-block" class="text-center pt-2 mb-3 border-t border-dashed border-black" style="display:none">
            <p class="text-[10px] uppercase tracking-wide font-bold">FBR INVOICE #: <span id="receipt-fbr-number" class="font-black"></span></p>
            <img id="receipt-fbr-qr" alt="" style="width:30mm;height:30mm;margin:6px auto 2px;display:block">
            <p class="text-[9px] text-gray-500 font-bold">Verify through FBR Tax Asaan app or SMS 9966</p>
        </div>

        <div class="text-center space-y-0.5 pt-1">
            <p class="font-black text-[11px] tracking-tight">THANK YOU FOR YOUR VISIT!</p>
            <p class="text-[9px] text-gray-500 font-bold">No Exchange / No Refund without Receipt</p>
            <div class="text-[8px] tracking-widest text-gray-400 pt-2 border-t border-gray-200 mt-2">
                SOFTWARE BY MACARON ENGINE V1.0
            </div>
        </div>
    </div>

    <!-- Production Order Ticket Template -->
    <div id="kitchen-receipt" class="hidden print:block bg-white text-black p-1 w-[80mm] font-mono text-[12px] leading-relaxed antialiased">
        <style type="text/css" media="print">
            @page { size: 80mm auto; margin: 0mm; }
            body { background: white; color: black; margin: 0; padding: 0; font-family: 'Courier New', monospace; }
            #kitchen-receipt { display: block !important; width: 80mm; padding: 3mm; box-sizing: border-box; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .flex { display: flex; }
            .grid { display: grid; }
            .grid-cols-12 { grid-template-columns: repeat(12, minmax(0, 1fr)); }
            .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .col-span-2 { grid-column: span 2 / span 2; }
            .col-span-3 { grid-column: span 3 / span 3; }
            .col-span-4 { grid-column: span 4 / span 4; }
            .col-span-7 { grid-column: span 7 / span 7; }
            .col-span-8 { grid-column: span 8 / span 8; }
            .col-span-10 { grid-column: span 10 / span 10; }
            .gap-y-0\.5 { row-gap: 2px; }
            .gap-y-1 { row-gap: 4px; }
            .font-black { font-weight: 900; }
            .font-bold { font-weight: 700; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            .text-gray-600 { color: #4b5563; }
            .text-gray-500 { color: #6b7280; }
            .text-gray-400 { color: #9ca3af; }
            .bg-black { background-color: #000; }
            .bg-gray-100 { background-color: #f3f4f6; }
            .text-white { color: #fff; }
            .uppercase { text-transform: uppercase; }
            .underline { text-decoration: underline; }
            .border { border-width: 1px; border-style: solid; }
            .border-b { border-bottom-width: 1px; border-style: solid; }
            .border-b-2 { border-bottom-width: 2px; border-style: solid; }
            .border-t { border-top-width: 1px; border-style: solid; }
            .border-t-2 { border-top-width: 2px; border-style: solid; }
            .border-y { border-top-width: 1px; border-bottom-width: 1px; border-style: solid; }
            .border-dashed { border-style: dashed; }
            .border-black { border-color: #000; }
            .p-1 { padding: 4px; }
            .p-2 { padding: 8px; }
            .px-1 { padding-left: 4px; padding-right: 4px; }
            .py-1 { padding-top: 4px; padding-bottom: 4px; }
            .pb-1 { padding-bottom: 4px; }
            .pb-2 { padding-bottom: 8px; }
            .pb-3 { padding-bottom: 12px; }
            .pt-1 { padding-top: 4px; }
            .pt-2 { padding-top: 8px; }
            .mb-1 { margin-bottom: 4px; }
            .mb-2 { margin-bottom: 8px; }
            .mb-3 { margin-bottom: 12px; }
            .mb-4 { margin-bottom: 16px; }
            .mt-1 { margin-top: 4px; }
            .mt-2 { margin-top: 8px; }
            .my-2 { margin-top: 8px; margin-bottom: 8px; }
            .text-\[9px\] { font-size: 9px; }
            .text-\[10px\] { font-size: 10px; }
            .text-\[11px\] { font-size: 11px; }
            .text-\[12px\] { font-size: 12px; }
            .text-\[14px\] { font-size: 14px; }
            .text-lg { font-size: 18px; }
            .text-xl { font-size: 20px; }
            .text-2xl { font-size: 24px; }
            .tracking-tight { letter-spacing: -0.025em; }
            .tracking-tighter { letter-spacing: -0.05em; }
            .tracking-wide { letter-spacing: 0.025em; }
            .tracking-widest { letter-spacing: 0.1em; }
            .leading-relaxed { line-height: 1.625; }
            .break-words { word-break: break-word; }
            .antialiased { -webkit-font-smoothing: antialiased; }
            .items-start { align-items: flex-start; }
            .items-center { align-items: center; }
            .justify-between { justify-content: space-between; }
            .hidden { display: none; }
            .space-y-1 > * + * { margin-top: 4px; }
            .space-y-1\.5 > * + * { margin-top: 6px; }
        </style>

        <div class="text-center pt-1 pb-3 mb-2 border-b-2 border-black">
            <h2 class="text-2xl font-black tracking-tighter uppercase">PRODUCTION ORDER</h2>
            <p id="kot-type" class="font-bold text-[12px] uppercase tracking-wide"></p>
        </div>

        <div class="flex justify-between font-black text-[12px] pb-1 mb-1 border-b border-dashed border-black">
            <span id="kot-token">TOKEN: #0000</span>
            <span id="kot-table">Counter Order</span>
        </div>

        <div class="text-center border-y border-black my-2 py-1 mb-2">
            <p class="font-bold text-[11px]">{{ date('h:i A') }}</p>
        </div>

        <div id="kot-customer-box" class="grid grid-cols-2 gap-y-0.5 text-[11px] pt-1 pb-2 mb-2 border-b border-dashed border-black" style="display:none">
            <div class="col-span-2"><span class="font-bold uppercase underline">Customer Details:</span></div>
            <div><span class="text-gray-600">NAME:</span> <span id="kot-customer-name" class="font-bold"></span></div>
            <div class="text-right"><span class="text-gray-600">PHONE:</span> <span id="kot-customer-phone" class="font-bold"></span></div>
        </div>

        <div class="grid grid-cols-12 font-black text-[11px] border-b-2 border-black pb-2 mb-1">
            <div class="col-span-10">ITEM</div>
            <div class="col-span-2 text-center">QTY</div>
        </div>

        <div id="kot-items" class="space-y-1.5 pt-0.5 pb-3 mb-1 border-b border-dashed border-black">
            <!-- Dynamic Items -->
        </div>

        <div class="text-center pt-2 pb-1">
            <p class="font-black text-[11px] uppercase tracking-widest">*** PRODUCTION COPY ***</p>
            <p class="text-[9px] text-gray-500 mt-1">{{ date('d-M-Y h:i A') }}</p>
        </div>
    </div>

    <!-- Z-Report Template (End of Day) -->
    <div id="z-report" class="hidden print:block bg-white text-black p-0 w-[80mm] font-mono text-[12px] leading-tight mx-auto">
        <style type="text/css" media="print">
            @page { size: 80mm auto; margin: 0mm; }
            body { background: white; color: black; margin: 0; padding: 0; font-family: 'Courier New', monospace; }
            #z-report { display: block !important; width: 80mm; padding: 5mm; box-sizing: border-box; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .flex { display: flex; }
            .justify-between { justify-content: space-between; }
            .text-center { text-align: center; }
            .text-red-600 { color: #dc2626; }
            .font-black { font-weight: 900; }
            .font-bold { font-weight: 700; }
            .italic { font-style: italic; }
            .uppercase { text-transform: uppercase; }
            .text-2xl { font-size: 24px; }
            .text-lg { font-size: 18px; }
            .text-\[10px\] { font-size: 10px; }
            .text-\[12px\] { font-size: 12px; }
            .text-\[13px\] { font-size: 13px; }
            .text-\[14px\] { font-size: 14px; }
            .tracking-widest { letter-spacing: 0.1em; }
            .leading-tight { line-height: 1.25; }
            .space-y-2 > * + * { margin-top: 8px; }
            .space-y-3 > * + * { margin-top: 12px; }
            .mb-6 { margin-bottom: 24px; }
            .mt-2 { margin-top: 8px; }
            .mt-10 { margin-top: 40px; }
            .p-0 { padding: 0; }
            .p-2 { padding: 8px; }
            .pb-2 { padding-bottom: 8px; }
            .pt-2 { padding-top: 8px; }
            .pt-4 { padding-top: 16px; }
            .border { border-width: 1px; border-style: solid; }
            .border-b { border-bottom-width: 1px; border-style: solid; }
            .border-b-4 { border-bottom-width: 4px; border-style: solid; }
            .border-t-2 { border-top-width: 2px; border-style: solid; }
            .border-black { border-color: #000; }
            .border-black\/10 { border-color: rgba(0, 0, 0, 0.1); }
            .rounded { border-radius: 4px; }
            .bg-black\/5 { background-color: rgba(0, 0, 0, 0.05); }
            .hidden { display: none; }
        </style>
        
        <div class="text-center mb-6 border-b-4 border-black pb-2">
            <h2 class="text-2xl font-black uppercase">Z-REPORT</h2>
            <p class="font-bold uppercase">End of Day Summary</p>
        </div>

        <div class="space-y-2 mb-6 text-[13px]">
            <div class="flex justify-between">
                <span>User:</span>
                <span class="font-bold" id="z-user">{{ auth()->user()->name ?? 'Cashier' }}</span>
            </div>
            <div class="flex justify-between">
                <span>Started:</span>
                <span id="z-start">00:00</span>
            </div>
            <div class="flex justify-between border-b border-black pb-2">
                <span>Closed:</span>
                <span id="z-end">00:00</span>
            </div>
        </div>

        <div class="space-y-3 mb-6">
            <div class="flex justify-between">
                <span>OPENING CASH</span>
                <span id="z-opening">0.00</span>
            </div>
            <div class="flex justify-between">
                <span>NET CASH SALES</span>
                <span id="z-expected">0.00</span>
            </div>
            <div class="flex justify-between font-black border-t-2 border-black pt-2 text-[14px]">
                <span>TOTAL EXPECTED</span>
                <span id="z-total-expected">0.00</span>
            </div>
        </div>

        <div class="space-y-3 mb-6 bg-black/5 p-2 rounded border border-black/10">
            <div class="flex justify-between font-black">
                <span>ACTUAL DECLARED</span>
                <span id="z-declared">0.00</span>
            </div>
            <div class="flex justify-between text-lg font-black border-t-2 border-black pt-2 mt-2">
                <span>VARIANCE</span>
                <span id="z-variance">0.00</span>
            </div>
        </div>

        <div class="text-center mt-10 border-t-2 border-black pt-4">
            <p class="font-bold uppercase tracking-widest">--- AUDIT COMPLETE ---</p>
            <p class="text-[10px] mt-2 italic">Printed on: <span id="z-printed-on">{{ date('d-M-Y h:i A') }}</span></p>
        </div>
    </div>

    <script>
        /**
         * Scoped Printing Hack
         * Captures current body, replaces with target, prints, and restores.
         */
        function scopedPrint(elementId, dataPopulator) {
            const targetElement = document.getElementById(elementId);
            
            if (!targetElement) {
                console.error(`Element ${elementId} not found`);
                return;
            }

            // Populate data if callback provided
            if (typeof dataPopulator === 'function') {
                dataPopulator(targetElement);
            }

            // Standalone copy has no Tailwind preflight; z-report's width math needs border-box
            const printHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Print</title><style>*{box-sizing:border-box}</style></head><body>' + targetElement.outerHTML + '</body></html>';

            // Electron shell: silent print through the main process (the native
            // dialog cannot preview or print subframes, so receipts never came out)
            if (window.macaronPos && window.macaronPos.printHtml) {
                window.macaronPos.printHtml(printHtml).catch(() => {});
                return;
            }

            // Create a hidden iframe for printing (avoids Alpine scope destruction)
            const iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.right = '-9999px';
            iframe.style.top = '0';
            iframe.style.width = '80mm';
            iframe.style.height = '1px';
            iframe.style.border = '0';
            document.body.appendChild(iframe);

            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(printHtml);
            iframeDoc.close();

            iframe.contentWindow.focus();
            iframe.contentWindow.print();

            setTimeout(() => {
                document.body.removeChild(iframe);
            }, 1000);
        }

        function printStampDate(d) {
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).replace(/ /g, '-');
        }

        function printStampTime(d) {
            return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        }

        function formatReceiptQty(qty, uom) {
            const n = parseFloat(parseFloat(qty).toFixed(3));
            return n + ' ' + ((uom || '').toUpperCase() === 'KG' ? 'kg' : 'pcs');
        }

        function populateThermalReceipt(element, orderData) {
            const curSym = orderData.currencySymbol || '{{ $settings->currency_symbol ?? 'Rs.' }}';
            element.querySelector('#receipt-token').innerText = '#' + (orderData.token || '0000');
            element.querySelector('#receipt-type').innerText = orderData.orderType || 'COUNTER';

            element.querySelector('#receipt-void-band').style.display = orderData.status === 'cancelled' ? 'block' : 'none';

            const when = orderData.date ? new Date(orderData.date) : new Date();
            element.querySelector('#receipt-date').innerText = printStampDate(when);
            element.querySelector('#receipt-time').innerText = printStampTime(when);
            
            const itemsContainer = element.querySelector('#receipt-items');
            itemsContainer.innerHTML = '';
            
            orderData.items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'grid grid-cols-12 text-[12px] items-start';
                div.innerHTML = `
                    <div class="col-span-7 font-bold tracking-tight break-words">${item.name}</div>
                    <div class="col-span-2 text-center font-bold">${formatReceiptQty(item.quantity, item.uom)}</div>
                    <div class="col-span-3 text-right font-bold">${(item.price * item.quantity).toFixed(2)}</div>
                `;
                itemsContainer.appendChild(div);
            });
            
            element.querySelector('#receipt-subtotal').innerText = orderData.subtotal.toFixed(2);
            element.querySelector('#receipt-tax').innerText = orderData.taxTotal.toFixed(2);
            element.querySelector('#receipt-total').innerHTML = curSym + ' ' + orderData.grandTotal.toFixed(2);

            // Customer Info
            const custRow = element.querySelector('#receipt-customer-row');
            const phoneRow = element.querySelector('#receipt-phone-row');
            if (orderData.customerName) {
                custRow.style.display = 'block';
                element.querySelector('#receipt-customer-name').innerText = orderData.customerName;
            } else {
                custRow.style.display = 'none';
            }
            if (orderData.customerPhone) {
                phoneRow.style.display = 'block';
                element.querySelector('#receipt-customer-phone').innerText = orderData.customerPhone;
            } else {
                phoneRow.style.display = 'none';
            }

            // Discount
            const discRow = element.querySelector('#receipt-discount-row');
            if (orderData.discount && orderData.discount > 0) {
                discRow.style.display = 'grid';
                element.querySelector('#receipt-discount').innerText = orderData.discount.toFixed(2);
            } else {
                discRow.style.display = 'none';
            }

            // Points Redeemed
            const ptsRow = element.querySelector('#receipt-points-row');
            if (orderData.pointsRedeemed && orderData.pointsRedeemed > 0) {
                ptsRow.style.display = 'grid';
                element.querySelector('#receipt-points').innerText = orderData.pointsRedeemed.toFixed(2);
            } else {
                ptsRow.style.display = 'none';
            }

            // Payment Method
            const payMethod = (orderData.paymentMethod || 'cash').toLowerCase();
            element.querySelector('#receipt-payment-method').innerText = payMethod.toUpperCase();

            // Cash received & change (cash sales)
            const cashBlock = element.querySelector('#receipt-cash-block');
            const tendered = parseFloat(orderData.amountTendered || 0);
            if (payMethod === 'cash' && tendered > 0) {
                cashBlock.style.display = 'block';
                element.querySelector('#receipt-cash-received').innerText = tendered.toFixed(2);
                element.querySelector('#receipt-change').innerText = parseFloat(orderData.changeAmount || 0).toFixed(2);
            } else {
                cashBlock.style.display = 'none';
            }

            // Bank/terminal reference (card / raast / wallet)
            const refRow = element.querySelector('#receipt-ref-row');
            if (payMethod !== 'cash' && orderData.transactionReference) {
                refRow.style.display = 'block';
                element.querySelector('#receipt-ref').innerText = orderData.transactionReference;
            } else {
                refRow.style.display = 'none';
            }

            // FBR verification block — only for invoices FBR has actually accepted
            const fbrBlock = element.querySelector('#receipt-fbr-block');
            if (orderData.fbrInvoiceNumber) {
                fbrBlock.style.display = 'block';
                element.querySelector('#receipt-fbr-number').innerText = orderData.fbrInvoiceNumber;
                const fbrQr = element.querySelector('#receipt-fbr-qr');
                if (orderData.fbrQr) {
                    fbrQr.src = orderData.fbrQr;
                    fbrQr.style.display = 'block';
                } else {
                    fbrQr.style.display = 'none';
                }
            } else {
                fbrBlock.style.display = 'none';
            }
        }

        function populateKitchenReceipt(element, orderData) {
            element.querySelector('#kot-token').innerText = 'TOKEN: #' + (orderData.token || '0000');
            element.querySelector('#kot-type').innerText = orderData.orderType || 'COUNTER';
            element.querySelector('#kot-table').innerText = orderData.customerName ? 'Customer: ' + orderData.customerName : 'Walk-in Customer';
            
            const itemsContainer = element.querySelector('#kot-items');
            itemsContainer.innerHTML = '';
            
            orderData.items.forEach((item, i) => {
                const div = document.createElement('div');
                div.className = 'grid grid-cols-12 text-[12px] items-start' + (i % 2 === 1 ? ' bg-gray-100' : '');
                div.innerHTML = `
                    <div class="col-span-10 font-bold tracking-tight break-words px-1">${item.name}</div>
                    <div class="col-span-2 text-center font-black text-[14px]">${formatReceiptQty(item.quantity, item.uom)}</div>
                `;
                itemsContainer.appendChild(div);
            });

            // Customer Info
            const customerBox = element.querySelector('#kot-customer-box');
            if (orderData.customerName || orderData.customerPhone) {
                customerBox.style.display = 'grid';
                element.querySelector('#kot-customer-name').innerText = orderData.customerName || 'N/A';
                element.querySelector('#kot-customer-phone').innerText = orderData.customerPhone || 'N/A';
            } else {
                customerBox.style.display = 'none';
            }
        }

        function printReceipt(orderData, onComplete) {
            scopedPrint('thermal-receipt', (el) => populateThermalReceipt(el, orderData));
            if (typeof onComplete === 'function') {
                setTimeout(onComplete, 800);
            }
        }

        function printKOT(orderData) {
            scopedPrint('kitchen-receipt', (el) => populateKitchenReceipt(el, orderData));
        }

        function printZReport(shiftData) {
            scopedPrint('z-report', (el) => {
                // expected_cash already includes the opening float: net = expected - opening
                const netCashSales = shiftData.expected - shiftData.opening;
                el.querySelector('#z-user').innerText = shiftData.user;
                el.querySelector('#z-start').innerText = shiftData.start;
                el.querySelector('#z-end').innerText = shiftData.end;
                el.querySelector('#z-opening').innerText = shiftData.opening.toFixed(2);
                el.querySelector('#z-expected').innerText = netCashSales.toFixed(2);
                el.querySelector('#z-total-expected').innerText = shiftData.expected.toFixed(2);
                el.querySelector('#z-declared').innerText = shiftData.declared.toFixed(2);
                
                const variance = shiftData.declared - shiftData.expected;
                const varianceEl = el.querySelector('#z-variance');
                varianceEl.innerText = (variance >= 0 ? '+' : '') + variance.toFixed(2);
                varianceEl.className = variance < 0 ? 'text-lg font-black text-red-600' : 'text-lg font-black';
                el.querySelector('#z-printed-on').innerText = printStampDate(new Date()) + ' ' + printStampTime(new Date());
            });
        }

        function posEngine() {
            return {
                currentTime: '',
                sidebarOpen: localStorage.getItem('macaron-sidebar') !== 'closed',
                searchQuery: '',
                selectedCategory: 'all',
                cart: [],
                isSubmitting: false,
                checkoutToken: crypto.randomUUID(),
                scannerBuffer: '',
                scannerLastKeyAt: 0,
                orderType: 'COUNTER',
                discount: 0,
                taxRate: {{ ((float) ($config->tax_rate ?? 0)) / 100 }},
                cardEnabled: {{ $config->card_enabled ? 'true' : 'false' }},
                raastEnabled: {{ ($config->raast_enabled && $config->raast_qr_path) ? 'true' : 'false' }},
                paymentMethod: 'cash',
                amountTendered: 0,
                transactionReference: '',
                customerName: '',
                customerPhone: '',
                selectedCustomer: null,
                searchingCustomer: false,
                pointsRedeemed: 0,
                weightProduct: null,
                customWeight: 0.5,
                products: @json($items ?? []),
                orderHistory: [],
                toast: { show: false, title: '', message: '', canUndo: false },
                showOrderPopup: false,
                lastOrderNumber: '',

                init() {
                    this.updateClock();
                    setInterval(() => this.updateClock(), 1000);
                    if (this.products.length) this.restoreCheckoutDraft();
                },

                saveCheckoutDraft() {
                    sessionStorage.setItem('macaron-checkout-draft', JSON.stringify({
                        cart: this.cart,
                        orderType: this.orderType,
                        paymentMethod: this.paymentMethod,
                        amountTendered: this.amountTendered,
                        transactionReference: this.transactionReference,
                        customerName: this.customerName,
                        customerPhone: this.customerPhone,
                        pointsRedeemed: this.pointsRedeemed,
                        checkoutToken: this.checkoutToken,
                    }));
                },

                restoreCheckoutDraft() {
                    try {
                        const draft = JSON.parse(sessionStorage.getItem('macaron-checkout-draft'));
                        if (!draft?.cart?.length) return;
                        this.cart = draft.cart;
                        this.orderType = draft.orderType || 'COUNTER';
                        this.paymentMethod = draft.paymentMethod || 'cash';
                        this.amountTendered = Number(draft.amountTendered || 0);
                        this.transactionReference = draft.transactionReference || '';
                        this.customerName = draft.customerName || '';
                        this.customerPhone = draft.customerPhone || '';
                        this.pointsRedeemed = Number(draft.pointsRedeemed || 0);
                        this.checkoutToken = draft.checkoutToken || crypto.randomUUID();
                        this.showToast('Bill restored', 'Your unfinished checkout was recovered.', false);
                    } catch (_) {
                        sessionStorage.removeItem('macaron-checkout-draft');
                    }
                },

                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                    document.documentElement.classList.toggle('nav-collapsed', !this.sidebarOpen);
                    localStorage.setItem('macaron-sidebar', this.sidebarOpen ? 'open' : 'closed');
                },

                updateClock() {
                    const now = new Date();
                    this.currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                },

                handleScannerKey(event) {
                    if (!this.products.length) return;
                    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
                    const now = Date.now();
                    if (event.key === 'Enter') {
                        if (this.scannerBuffer.length >= 3 && now - this.scannerLastKeyAt < 250) {
                            const product = this.products.find(item => item.barcode === this.scannerBuffer);
                            product ? this.addItem(product) : this.showToast('Barcode not found', this.scannerBuffer, false);
                            event.preventDefault();
                        }
                        this.scannerBuffer = '';
                        return;
                    }
                    if (event.key.length !== 1) return;
                    this.scannerBuffer = now - this.scannerLastKeyAt < 100 ? this.scannerBuffer + event.key : event.key;
                    this.scannerLastKeyAt = now;
                },

                async searchCustomer(phone) {
                    if (!phone || phone.length < 4) {
                        this.selectedCustomer = null;
                        this.pointsRedeemed = 0;
                        return;
                    }
                    this.searchingCustomer = true;
                    try {
                        const res = await fetch('{{ route("customers.search") }}?phone=' + encodeURIComponent(phone));
                        const data = await res.json();
                        if (data.customer) {
                            this.selectedCustomer = data.customer;
                            this.customerName = data.customer.name;
                        } else {
                            this.selectedCustomer = null;
                            this.pointsRedeemed = 0;
                        }
                    } catch (e) {
                        this.selectedCustomer = null;
                    } finally {
                        this.searchingCustomer = false;
                    }
                },

                addItem(product) {
                    if (!product.is_saleable) {
                        this.showToast('Item unavailable', product.stock_status + ' items cannot be sold.', false);
                        return;
                    }
                    const weighted = product.uom === 'KG' || product.uom === 'Gram';
                    if (weighted) {
                        this.weightProduct = product;
                        this.customWeight = 0.5;
                        return;
                    }
                    this.addToCart(product, 1);
                },

                selectWeight(weight) {
                    const entered = parseFloat(weight);
                    if (!this.weightProduct || !entered || entered <= 0) return;
                    this.addToCart(this.weightProduct, entered);
                    this.weightProduct = null;
                },

                addToCart(product, entered) {
                    const existing = this.cart.find(i => i.id === product.id);
                    if (existing) {
                        existing.quantity = parseFloat((parseFloat(existing.quantity) + entered).toFixed(3));
                        // Trigger reactivity for nested property
                        this.cart = [...this.cart];
                    } else {
                        this.cart.push({
                            id: product.id,
                            name: product.name,
                            price: parseFloat(product.price),
                            image: product.image_path,
                            quantity: entered,
                            uom: product.uom
                        });
                    }
                },

                removeItem(productId) {
                    this.cart = this.cart.filter(i => i.id !== productId);
                },

                updateQuantity(productId, amount) {
                    const item = this.cart.find(i => i.id === productId);
                    if (item) {
                        item.quantity += amount;
                        if (item.quantity <= 0) {
                            this.removeItem(productId);
                        } else {
                            // Trigger reactivity for nested property
                            this.cart = [...this.cart];
                        }
                    }
                },

                get subtotal() {
                    return this.money(this.cart.reduce((sum, item) => sum + (parseFloat(item.price) * parseFloat(item.quantity)), 0));
                },

                get taxTotal() {
                    return this.money(this.subtotal * this.taxRate);
                },

                get grandTotal() {
                    return this.money((this.subtotal + this.taxTotal) - parseFloat(this.discount || 0));
                },

                get effectiveTotal() {
                    const points = this.selectedCustomer ? parseFloat(this.pointsRedeemed || 0) : 0;
                    return this.money(Math.max(0, this.grandTotal - points));
                },

                get cashShortfall() {
                    return this.money(Math.max(0, this.effectiveTotal - Number(this.amountTendered || 0)));
                },

                get cashChange() {
                    return this.money(Math.max(0, Number(this.amountTendered || 0) - this.effectiveTotal));
                },

                get canCompletePayment() {
                    if (this.cart.length === 0) return false;
                    if (this.paymentMethod === 'cash') return this.cashShortfall === 0;
                    return this.transactionReference.trim().length > 0;
                },

                currencySymbol: '{{ $settings->currency_symbol ?? 'Rs.' }}',

                formatCurrency(value) {
                    return this.currencySymbol + ' ' + parseFloat(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                money(value) {
                    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
                },

                async completePayment() {
                    if (this.cart.length === 0 || this.isSubmitting) return;
                    if (this.paymentMethod !== 'cash' && !this.transactionReference.trim()) {
                        this.showToast('Reference required', 'Confirm the bank payment and enter its reference.', false);
                        return;
                    }

                    // Save snapshot for Undo
                    const orderSnapshot = {
                        id: 'ORD-' + Math.floor(Math.random() * 10000),
                        items: JSON.parse(JSON.stringify(this.cart)),
                        grandTotal: this.grandTotal,
                        orderType: this.orderType
                    };

                    this.orderHistory.push(orderSnapshot);
                    if (this.orderHistory.length > 5) this.orderHistory.shift();

                    // Send to Backend
                    try {
                        this.isSubmitting = true;
                        this.saveCheckoutDraft();
                        const response = await fetch('{{ route("orders.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                order_type: this.orderType,
                                items: this.cart.map(i => ({ id: i.id, quantity: i.quantity })),
                                discount: this.discount,
                                subtotal: this.subtotal,
                                tax: this.taxTotal,
                                grand_total: this.effectiveTotal,
                                payment_method: this.paymentMethod,
                                amount_tendered: this.amountTendered,
                                change_amount: Math.max(0, this.amountTendered - this.effectiveTotal),
                                transaction_reference: this.transactionReference,
                                customer_name: this.customerName,
                                customer_phone: this.customerPhone,
                                customer_id: this.selectedCustomer?.id || null,
                                points_redeemed: this.pointsRedeemed
                                ,checkout_token: this.checkoutToken
                            })
                        });

                        if (response.status === 419) {
                            this.showToast('Session expired', 'Bill is saved. Reloading secure login…', false);
                            setTimeout(() => window.location.reload(), 1500);
                            return;
                        }

                        const result = await response.json();
                        if (result.success) {
                            const orderData = {
                                token: result.order.order_number.split('-')[1],
                                orderType: this.orderType,
                                date: result.order.created_at,
                                items: this.cart,
                                subtotal: parseFloat(result.order.subtotal),
                                taxTotal: parseFloat(result.order.tax),
                                grandTotal: parseFloat(result.order.grand_total),
                                customerName: this.customerName,
                                customerPhone: this.customerPhone,
                                discount: parseFloat(this.discount),
                                paymentMethod: this.paymentMethod,
                                amountTendered: parseFloat(this.amountTendered || 0),
                                changeAmount: Math.max(0, this.amountTendered - this.effectiveTotal),
                                transactionReference: this.transactionReference,
                                status: result.order.status || 'completed',
                                pointsRedeemed: parseFloat(this.pointsRedeemed || 0),
                                fbrInvoiceNumber: result.order.fbr_invoice_number || null,
                                fbrQr: result.fbr_qr || null,
                                currencySymbol: this.currencySymbol
                            };

                            if (!result.receipt_printed) {
                                printReceipt(orderData);
                            }

                            // Step C: Clear cart and fields
                            this.cart = [];
                            this.amountTendered = 0;
                            this.customerName = '';
                            this.customerPhone = '';
                            this.selectedCustomer = null;
                            this.pointsRedeemed = 0;
                            this.transactionReference = '';
                            this.paymentMethod = 'cash';
                            this.checkoutToken = crypto.randomUUID();
                            sessionStorage.removeItem('macaron-checkout-draft');
                            this.lastOrderNumber = result.order.order_number;
                            this.showOrderPopup = true;
                        } else {
                            this.showToast('Error', result.message || 'Failed to save order.', false);
                        }
                    } catch (error) {
                        this.showToast('Error', 'Failed to save order to database.', false);
                    } finally {
                        this.isSubmitting = false;
                    }
                },

                undoLastOrder() {
                    if (this.orderHistory.length === 0) return;
                    const lastOrder = this.orderHistory.pop();
                    this.cart = lastOrder.items;
                    this.orderType = lastOrder.orderType;
                    this.showToast('Order Restored', 'Active cart has been recovered.', false);
                },

                showToast(title, message, canUndo) {
                    this.toast.show = true;
                    this.toast.title = title;
                    this.toast.message = message;
                    this.toast.canUndo = canUndo;
                    
                    const timeout = canUndo ? 8000 : 3000;
                    setTimeout(() => { this.toast.show = false; }, timeout);
                },

                async completeOrder() {
                    await this.completePayment();
                }
            }
        }
    </script>
    
    <!-- Custom Toast Notification -->
    <div x-show="toast.show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-10"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-10"
         class="fixed bottom-8 left-8 bg-surface border border-primary/20 p-5 rounded-2xl shadow-2xl z-[100] flex items-center gap-6 backdrop-blur-xl bg-opacity-90 min-w-[320px]"
         style="display: none;">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
            <span class="material-symbols-rounded text-primary text-2xl" x-text="toast.canUndo ? 'check_circle' : 'info'"></span>
        </div>
        <div class="flex-1">
            <p class="font-black text-sm text-primary uppercase tracking-widest" x-text="toast.title"></p>
            <p class="text-xs text-on-surface-variant mt-1 font-medium" x-text="toast.message"></p>
        </div>
        <button x-show="toast.canUndo" 
                @click="undoLastOrder()" 
                class="bg-primary text-on-primary px-4 py-2 text-[10px] font-black uppercase tracking-widest rounded-xl hover:brightness-110 transition-all active:scale-95 shadow-lg shadow-primary/20">
            UNDO
        </button>
    </div>

    <!-- Order Placed Modal -->
    <div x-show="showOrderPopup" 
         x-transition.opacity
         class="fixed inset-0 z-[150] flex items-center justify-center bg-black/60 backdrop-blur-sm"
         style="display: none;">
        <div @click.away="showOrderPopup = false"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-surface border border-outline rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center flex flex-col items-center">
            
            <div class="w-20 h-20 rounded-full bg-primary/20 flex items-center justify-center mb-6">
                <span class="material-symbols-rounded text-primary text-5xl">check_circle</span>
            </div>
            
            <h2 class="text-2xl font-black text-white uppercase tracking-tight mb-2">Order Placed!</h2>
            <p class="text-on-surface-variant text-sm font-bold mb-6">Invoice <span x-text="lastOrderNumber" class="text-primary"></span> generated successfully.</p>
            
            <button @click="showOrderPopup = false" class="w-full bg-primary text-on-primary h-12 rounded-xl font-black uppercase tracking-widest hover:brightness-110 transition-all active:scale-95 shadow-lg shadow-primary/20">
                New Order
            </button>
        </div>
    </div>
    </div>
</body>
</html>
