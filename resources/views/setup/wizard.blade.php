<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- View-transition opt-in must be inline: Chromium evaluates it before external CSS arrives --}}
    <style>@view-transition { navigation: auto; }</style>
    <title>Setup - Macaron</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-dark.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-[#120E0B] text-[#e2e2e8] min-h-screen flex items-center justify-center p-6">
    <div x-data="{ step: 1 }" class="w-full max-w-5xl bg-[#1D1713] rounded-3xl border border-white/10 shadow-2xl flex flex-col md:flex-row relative overflow-hidden">
        
        <!-- Left Hero Pane -->
        <div class="md:w-5/12 bg-gradient-to-br from-[#120E0B] to-[#2A211B] p-8 flex flex-col justify-between border-r border-white/10 relative overflow-hidden min-h-[300px] md:min-h-auto">
            <div class="absolute inset-0 bg-[url('/images/setup_hero.png')] bg-cover bg-center opacity-40 mix-blend-overlay"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-[#120E0B] via-transparent to-transparent"></div>
            <div class="relative z-10">
                <span class="px-3 py-1 bg-[#F4B942]/10 border border-[#F4B942]/20 text-[#F4B942] text-[9px] font-black uppercase tracking-widest rounded-full">Guided Shop Setup</span>
                <h1 class="text-3xl font-black text-white mt-6 mb-2 tracking-tight uppercase">Macaron<br><span class="text-[#F4B942]">Setup</span></h1>
                <p class="text-xs text-[#CDBEAD] leading-relaxed">Set up fast counter billing, daily batch tracking, stock control and fiscal configuration for your sweets shop or bakery.</p>
            </div>
            
            <div class="relative z-10 mt-12 bg-[#120E0B]/80 backdrop-blur border border-white/5 rounded-2xl p-4">
                <p class="text-[9px] font-black text-[#F4B942] uppercase tracking-widest mb-1">Onboarding Status</p>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-white" x-text="'Step ' + step + ' of 3'"></span>
                    <span class="text-[9px] text-[#CDBEAD]">•</span>
                    <span class="text-[9px] text-[#F4B942] font-bold uppercase tracking-wider" x-text="step === 1 ? 'Owner Account' : (step === 2 ? 'Sweets Profile' : 'Counter Device')"></span>
                </div>
            </div>
        </div>

        <!-- Right Form Pane -->
        <div class="flex-1 p-8 md:p-12 relative flex flex-col justify-between">
            <div class="absolute top-0 left-0 w-full h-1 bg-[#120E0B]">
                <div class="h-full bg-[#F4B942] transition-all duration-500" :style="'width: ' + (step * 33.33) + '%'"></div>
            </div>

            @if($errors->any())
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 rounded-xl p-4 mb-6 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            <form action="{{ route('setup.process') }}" method="POST" x-ref="setupForm">
                @csrf
            
            <!-- Step 1: Admin & Store -->
            <div x-show="step === 1" x-transition.opacity class="space-y-6">
                <div class="text-center mb-8">
                    <h2 class="text-xl font-bold text-white">Owner / Administrator Account</h2>
                    <p class="text-xs text-[#CDBEAD] mt-1">This account controls products, staff, reports and fiscal settings.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-[#CDBEAD] uppercase tracking-widest">Full Name</label>
                        <input type="text" name="admin_name" required class="w-full bg-[#120E0B] border border-white/10 rounded-xl h-12 px-4 focus:ring-1 focus:ring-[#F4B942] outline-none text-sm">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-[#CDBEAD] uppercase tracking-widest">Email Address</label>
                        <input type="email" name="admin_email" required class="w-full bg-[#120E0B] border border-white/10 rounded-xl h-12 px-4 focus:ring-1 focus:ring-[#F4B942] outline-none text-sm">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-[#CDBEAD] uppercase tracking-widest">Master Password</label>
                    <input type="password" name="admin_password" required class="w-full bg-[#120E0B] border border-white/10 rounded-xl h-12 px-4 focus:ring-1 focus:ring-[#F4B942] outline-none text-sm">
                </div>

                <hr class="border-white/10 my-6">

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-[#CDBEAD] uppercase tracking-widest">Sweet Shop Name</label>
                        <input type="text" name="shop_name" required class="w-full bg-[#120E0B] border border-white/10 rounded-xl h-12 px-4 focus:ring-1 focus:ring-[#F4B942] outline-none text-sm">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-[#CDBEAD] uppercase tracking-widest">Phone Number</label>
                        <input type="text" name="phone_number" required class="w-full bg-[#120E0B] border border-white/10 rounded-xl h-12 px-4 focus:ring-1 focus:ring-[#F4B942] outline-none text-sm">
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="button" @click="step = 2" class="px-8 h-12 bg-[#F4B942] text-[#2B1900] font-black rounded-xl hover:brightness-110 transition-all uppercase tracking-widest text-xs">Next Step</button>
                </div>
            </div>

            <!-- Step 2: Persona Selection -->
            <div x-show="step === 2" style="display: none;" x-transition.opacity class="space-y-6">
                <div class="text-center mb-8">
                    <h2 class="text-xl font-bold text-white">Sweet Shop Profile</h2>
                    <p class="text-xs text-[#CDBEAD] mt-1">Starter categories cover loose mithai, bakery products, cakes, savoury items and gift boxes.</p>
                </div>

                <div class="grid grid-cols-1 gap-4" x-data="{ selected: 'sweets' }">
                    <input type="hidden" name="persona" :value="selected">
                    <button type="button" class="p-6 border border-[#F4B942] bg-[#F4B942]/10 rounded-xl flex items-center gap-5 text-left">
                        <div class="w-14 h-14 rounded-full bg-[#1D1713] flex items-center justify-center text-3xl">🍬</div>
                        <div>
                            <span class="font-bold text-base block">Sweets Shop / Bakery</span>
                            <span class="text-xs text-[#CDBEAD]">Counter sales • per kg and per piece • daily batches and wastage</span>
                        </div>
                    </button>
                </div>

                <div class="flex justify-between pt-4">
                    <button type="button" @click="step = 1" class="px-8 h-12 bg-[#120E0B] border border-white/10 text-white font-black rounded-xl hover:bg-white/5 transition-all uppercase tracking-widest text-xs">Back</button>
                    <button type="button" @click="step = 3" class="px-8 h-12 bg-[#F4B942] text-[#2B1900] font-black rounded-xl hover:brightness-110 transition-all uppercase tracking-widest text-xs">Next Step</button>
                </div>
            </div>

            <!-- Step 3: Counter device -->
            <div x-show="step === 3" style="display: none;" x-transition.opacity class="space-y-6">
                <div class="text-center mb-8">
                    <h2 class="text-xl font-bold text-white">Choose the Main Counter</h2>
                    <p class="text-xs text-[#CDBEAD] mt-1">Start on one reliable device. Payments and FBR are configured later by the Owner.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="p-4 border border-[#F4B942] bg-[#F4B942]/10 rounded-xl">
                        <span class="text-[9px] font-black text-[#F4B942] uppercase tracking-widest">Recommended</span>
                        <h3 class="font-bold mt-2">Windows PC / Touch POS</h3>
                        <p class="text-xs text-[#CDBEAD] mt-1">Full MVP: offline database, receipt printer, barcode scanner and cash drawer.</p>
                    </div>
                    <div class="p-4 border border-white/10 rounded-xl">
                        <h3 class="font-bold">Android POS Tablet</h3>
                        <p class="text-xs text-[#CDBEAD] mt-1">Browser can open Macaron. Built-in printer/card reader needs that exact device vendor's SDK and is not guaranteed.</p>
                    </div>
                    <div class="p-4 border border-white/10 rounded-xl">
                        <h3 class="font-bold">Tablet / Phone</h3>
                        <p class="text-xs text-[#CDBEAD] mt-1">Companion access on the same Wi-Fi. Not the primary offline server or supported receipt-printer host.</p>
                    </div>
                </div>

                <hr class="border-white/10 my-6">

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-[#CDBEAD] uppercase tracking-widest">80mm Receipt Printer IP (Optional)</label>
                    <input type="text" name="cashier_printer_ip" placeholder="e.g. 192.168.1.201 — leave blank to use browser print" class="w-full bg-[#120E0B] border border-white/10 rounded-xl h-12 px-4 focus:ring-1 focus:ring-[#F4B942] outline-none text-sm placeholder-white/20">
                    <p class="text-[10px] text-[#CDBEAD]">USB printers can be installed and shared in Windows later from Shop Settings.</p>
                </div>

                <div class="p-4 bg-[#120E0B] border border-white/10 rounded-xl text-xs text-[#CDBEAD]">
                    <strong class="text-white">MVP payment flow:</strong> Cash is ready. Card insert/swipe/tap uses the shop's separate bank terminal. Raast merchant QR is optional. Enable only what the shop owns from Shop Settings.
                </div>

                <div class="flex justify-between pt-4">
                    <button type="button" @click="step = 2" class="px-8 h-12 bg-[#120E0B] border border-white/10 text-white font-black rounded-xl hover:bg-white/5 transition-all uppercase tracking-widest text-xs">Back</button>
                    <button type="submit" class="px-8 h-12 bg-white text-black font-black rounded-xl hover:brightness-110 transition-all uppercase tracking-widest text-xs flex items-center gap-2 shadow-xl shadow-white/10">
                        Initialize System
                    </button>
                </div>
            </div>

        </form>
        </div> <!-- Right Form Pane -->
    </div> <!-- Overall Wrapper -->
</body>
</html>
