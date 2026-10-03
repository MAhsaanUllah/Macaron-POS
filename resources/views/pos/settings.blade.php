@extends('layouts.app')

@section('content')
<div class="flex-1 p-8 overflow-y-auto" x-data="{ activeTab: '{{ request()->query('tab', 'shop') }}' }">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black text-white uppercase tracking-tighter">System Settings</h1>
            <a href="{{ route('dashboard') }}" class="px-6 py-2 bg-active-surface text-on-surface text-xs font-black rounded-xl border border-outline hover:bg-outline transition-all uppercase tracking-widest">Back to POS</a>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-primary/10 border border-primary/20 text-primary rounded-xl text-xs font-bold uppercase tracking-wider">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 bg-error/10 border border-error/20 text-error rounded-xl text-xs font-bold uppercase tracking-wider">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 bg-error/10 border border-error/20 text-error rounded-xl text-xs font-bold uppercase tracking-wider">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Settings Tabs Navigation -->
        <div class="flex gap-2 border-b border-outline mb-8">
            <button type="button" @click="activeTab = 'shop'" :class="activeTab === 'shop' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-white'" class="px-6 py-3 border-b-2 font-black text-xs uppercase tracking-wider transition-colors outline-none">Shop Profile</button>
            <button type="button" @click="activeTab = 'payments'" :class="activeTab === 'payments' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-white'" class="px-6 py-3 border-b-2 font-black text-xs uppercase tracking-wider transition-colors outline-none">Payments</button>
            <button type="button" @click="activeTab = 'hardware'" :class="activeTab === 'hardware' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-white'" class="px-6 py-3 border-b-2 font-black text-xs uppercase tracking-wider transition-colors outline-none">Hardware & Fiscal</button>
            <button type="button" @click="activeTab = 'staff'" :class="activeTab === 'staff' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-white'" class="px-6 py-3 border-b-2 font-black text-xs uppercase tracking-wider transition-colors outline-none">Staff / Users</button>
        </div>

        <!-- Tab 1: Shop Profile -->
        <div x-show="activeTab === 'shop'" class="space-y-6">
            <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <!-- Branding Section -->
                <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl">
                    <h3 class="text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">Shop Branding</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Shop Name</label>
                            <input type="text" name="shop_name" value="{{ old('shop_name', $settings->shop_name) }}" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Currency Symbol</label>
                            <input type="text" name="currency_symbol" value="{{ old('currency_symbol', $settings->currency_symbol) }}" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>
                    </div>

                    <div class="mt-6 space-y-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Shop Logo</label>
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 rounded-2xl bg-background border border-outline flex items-center justify-center overflow-hidden">
                                @if($settings->logo_path)
                                    <img src="{{ asset('storage/' . $settings->logo_path) }}" class="w-full h-full object-contain">
                                @else
                                    <span class="material-symbols-rounded text-3xl text-on-surface-variant">image</span>
                                @endif
                            </div>
                            <input type="file" name="logo" class="text-xs text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-primary file:text-on-primary hover:file:brightness-110 cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Contact Section -->
                <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl">
                    <h3 class="text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">Contact & Address</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Phone Number</label>
                            <input type="text" name="phone_number" value="{{ old('phone_number', $settings->phone_number) }}" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Tax Number (NTN)</label>
                            <input type="text" name="tax_number" value="{{ old('tax_number', $settings->tax_number) }}" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>
                    </div>

                    <div class="mt-6 space-y-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Address</label>
                        <textarea name="address" rows="3" class="w-full bg-background border border-outline rounded-xl p-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">{{ old('address', $settings->address) }}</textarea>
                    </div>
                </div>

                <div class="bg-surface border border-outline rounded-2xl p-6">
                    <h3 class="text-primary text-sm font-bold mb-2">Optional Order Channels</h3>
                    <p class="text-sm text-on-surface-variant mb-5">Counter is always available. Enable only the channels this shop actually uses. Pickup orders can be received on the shop phone/WhatsApp number above.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center gap-4 min-h-14 p-4 bg-active-surface rounded-xl cursor-pointer">
                            <input type="hidden" name="pickup_enabled" value="0">
                            <input type="checkbox" name="pickup_enabled" value="1" {{ old('pickup_enabled', $config->pickup_enabled) ? 'checked' : '' }} class="w-5 h-5 rounded border-outline bg-background text-primary">
                            <span>
                                <span class="block text-sm font-bold text-on-surface">Pickup / WhatsApp Orders</span>
                                <span class="block text-xs text-on-surface-variant">Shows Pickup on the terminal.</span>
                            </span>
                        </label>
                        <label class="flex items-center gap-4 min-h-14 p-4 bg-active-surface rounded-xl cursor-pointer">
                            <input type="hidden" name="delivery_enabled" value="0">
                            <input type="checkbox" name="delivery_enabled" value="1" {{ old('delivery_enabled', $config->delivery_enabled) ? 'checked' : '' }} class="w-5 h-5 rounded border-outline bg-background text-primary">
                            <span>
                                <span class="block text-sm font-bold text-on-surface">Delivery Orders</span>
                                <span class="block text-xs text-on-surface-variant">Shows Delivery on the terminal.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full h-16 bg-primary text-on-primary font-black text-sm uppercase tracking-[0.3em] rounded-2xl shadow-2xl shadow-primary/20 hover:brightness-110 active:scale-[0.98] transition-all">
                    Save Profile
                </button>
            </form>
        </div>

        <div x-show="activeTab === 'payments'" style="display: none;">
            <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" name="shop_name" value="{{ $settings->shop_name }}">
                <div class="bg-surface border border-outline rounded-2xl p-6 space-y-6">
                    <div>
                        <h3 class="text-primary text-sm font-bold">Payment Methods</h3>
                        <p class="text-sm text-on-surface-variant mt-1">Cash is always available. Enable only methods the shop can verify.</p>
                    </div>
                    <div class="p-4 bg-active-surface rounded-xl">
                        <div class="flex items-center gap-3"><span class="material-symbols-rounded">payments</span><span class="font-bold">Cash</span><span class="ml-auto text-xs text-on-surface-variant">Always on</span></div>
                    </div>
                    <div class="p-4 bg-active-surface rounded-xl space-y-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="card_enabled" value="0">
                            <input type="checkbox" name="card_enabled" value="1" {{ old('card_enabled', $config->card_enabled) ? 'checked' : '' }} class="w-5 h-5 rounded border-outline bg-background text-primary">
                            <span><span class="block font-bold">Card / Tap machine</span><span class="block text-xs text-on-surface-variant">Cashier confirms payment on the bank terminal and enters its reference.</span></span>
                        </label>
                        <input type="text" name="card_terminal_name" value="{{ old('card_terminal_name', $config->card_terminal_name) }}" placeholder="Terminal label, e.g. HBL Counter 1" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm text-white">
                    </div>
                    <div class="p-4 bg-active-surface rounded-xl space-y-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="raast_enabled" value="0">
                            <input type="checkbox" name="raast_enabled" value="1" {{ old('raast_enabled', $config->raast_enabled) ? 'checked' : '' }} class="w-5 h-5 rounded border-outline bg-background text-primary">
                            <span><span class="block font-bold">Raast merchant QR</span><span class="block text-xs text-on-surface-variant">Upload the QR issued by the shop's bank/PSP. Customer can pay from any participating app.</span></span>
                        </label>
                        <div class="flex items-center gap-5">
                            @if($config->raast_qr_path)
                                <img src="{{ asset('storage/'.$config->raast_qr_path) }}" alt="Saved Raast QR" class="w-24 h-24 object-contain bg-white rounded-xl p-2">
                            @endif
                            <input type="file" name="raast_qr" accept="image/png,image/jpeg" class="text-xs text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-primary file:text-on-primary">
                        </div>
                        <p class="text-xs text-on-surface-variant">This version records a verified bank reference. Automatic confirmation needs an API from the selected bank/PSP.</p>
                    </div>
                </div>
                <button type="submit" class="w-full h-14 bg-primary text-on-primary font-black text-sm rounded-2xl">Save Payment Methods</button>
            </form>
        </div>

        <!-- Tab 2: Hardware & Fiscal -->
        <div x-show="activeTab === 'hardware'" style="display: none;" class="space-y-6">
            <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl space-y-5">
                <div>
                    <h3 class="text-primary text-sm font-bold">Counter Readiness</h3>
                    <p class="text-sm text-on-surface-variant mt-1">Supported pilot setup: Windows counter, 80mm ESC/POS receipt printer, USB barcode scanner and optional cash drawer.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center gap-3 p-4 bg-active-surface rounded-xl">
                        <span class="material-symbols-rounded {{ $hardwareStatus['receipt_printer'] ? 'text-primary' : 'text-on-surface-variant' }}">print</span>
                        <span><span class="block font-bold">Receipt printer</span><span class="text-xs text-on-surface-variant">{{ $hardwareStatus['receipt_printer'] ? 'Configured — run a test below' : 'Not configured' }}</span></span>
                    </div>
                    <div class="flex items-center gap-3 p-4 bg-active-surface rounded-xl">
                        <span class="material-symbols-rounded {{ $hardwareStatus['backup'] ? 'text-primary' : 'text-error' }}">database</span>
                        <span><span class="block font-bold">Local sales database</span><span class="text-xs text-on-surface-variant">{{ $hardwareStatus['backup'] ? 'Ready for offline billing and backup' : 'Database backup unavailable' }}</span></span>
                    </div>
                    <div class="flex items-center gap-3 p-4 bg-active-surface rounded-xl">
                        <span class="material-symbols-rounded {{ $hardwareStatus['fbr'] ? 'text-primary' : 'text-on-surface-variant' }}">receipt_long</span>
                        <span><span class="block font-bold">FBR submission</span><span class="text-xs text-on-surface-variant">{{ $hardwareStatus['fbr'] ? 'Enabled — merchant approval still required' : 'Off until merchant sandbox approval' }}</span></span>
                    </div>
                    <div class="flex items-center gap-3 p-4 bg-active-surface rounded-xl">
                        <span class="material-symbols-rounded text-on-surface-variant">cloud_off</span>
                        <span><span class="block font-bold">Cloud backup</span><span class="text-xs text-on-surface-variant">Not connected — download local backups below</span></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl" x-data="{ scan: '', checked: false }">
                    <h3 class="text-primary text-sm font-bold">Barcode Scanner Test</h3>
                    <p class="text-xs text-on-surface-variant mt-1 mb-4">USB scanners act like a keyboard. Click below and scan any product.</p>
                    <input x-model="scan" @input.debounce.150ms="checked = scan.trim().length > 2" @keydown.enter.prevent="checked = scan.trim().length > 2" type="text" placeholder="Scan barcode here" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm text-on-surface">
                    <p x-show="checked" class="text-primary text-xs font-bold mt-3"><span class="material-symbols-rounded text-sm align-middle">check_circle</span> Scanner input received: <span x-text="scan"></span></p>
                </div>
                <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl">
                    <h3 class="text-primary text-sm font-bold">Tablet / Second Counter</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Keep this Windows counter as the server. Connect the tablet to the same Wi-Fi and open this counter's LAN address in Chrome. Use a fixed LAN IP before a pilot.</p>
                    <p class="text-xs text-on-surface-variant mt-3">Internet is not required inside the shop, but the counter PC and Wi-Fi router must stay on.</p>
                </div>
            </div>

            <form action="{{ route('settings.update') }}" method="POST" class="space-y-6">
                @csrf
                <!-- Pass dummy Shop values so validation passes -->
                <input type="hidden" name="shop_name" value="{{ $settings->shop_name }}">
                
                <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl">
                    <h3 class="text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">Receipt Printer & Fiscal Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">FBR POS ID</label>
                            <input type="text" name="fbr_pos_id" value="{{ old('fbr_pos_id', $config->fbr_pos_id) }}" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">FBR Bearer Token</label>
                            <input type="password" name="fbr_bearer_token" value="" placeholder="Leave blank to keep saved token" autocomplete="new-password" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">FBR Environment</label>
                            <select name="fbr_environment" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                                <option value="sandbox" {{ old('fbr_environment', $config->fbr_environment) === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                <option value="production" {{ old('fbr_environment', $config->fbr_environment) === 'production' ? 'selected' : '' }}>Production</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Sales Tax Rate (%) — confirm with accountant</label>
                            <input type="number" name="tax_rate" min="0" max="100" step="0.01" value="{{ old('tax_rate', $config->tax_rate ?? 0) }}" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Cashier Discount Limit (%) — above this needs manager/owner</label>
                            <input type="number" name="discount_limit_pct" min="0" max="100" step="0.5" value="{{ old('discount_limit_pct', $config->discount_limit_pct ?? 15) }}" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Seller Province</label>
                            <input type="text" name="seller_province" value="{{ old('seller_province', $config->seller_province ?? 'Punjab') }}" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Sandbox Scenario ID</label>
                            <input type="text" name="fbr_scenario_id" value="{{ old('fbr_scenario_id', $config->fbr_scenario_id) }}" placeholder="e.g. SN001" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>

                        <label class="flex items-center gap-3 text-xs font-bold text-white">
                            <input type="hidden" name="fbr_enabled" value="0">
                            <input type="checkbox" name="fbr_enabled" value="1" {{ old('fbr_enabled', $config->fbr_enabled) ? 'checked' : '' }} class="rounded border-outline bg-background text-primary">
                            Enable FBR submission after sandbox approval
                        </label>

                        <div class="md:col-span-2 space-y-2">
                            <label class="flex items-center gap-3 text-xs font-bold text-white cursor-pointer">
                                <input type="hidden" name="lan_access_enabled" value="0">
                                <input type="checkbox" name="lan_access_enabled" value="1" {{ old('lan_access_enabled', $config->lan_access_enabled ?? false) ? 'checked' : '' }} class="rounded border-outline bg-background text-primary">
                                Allow tablet/phone access on this network
                            </label>
                            <p id="lanTabletHelp" class="text-xs text-on-surface-variant">
                                @if (!empty(env('MACARON_LAN_URL')))
                                    On this network, tablets/phones can open: <span class="font-mono select-all">{{ env('MACARON_LAN_URL') }}</span> — same login, cashier/manager/owner roles apply. Turn OFF to close access on next app start.
                                @else
                                    Works only while Macaron desktop is running with this option ON. Counter sales never depend on the network.
                                @endif
                            </p>
                            @if (!empty(env('MACARON_SHELL')))
                                <button type="button" id="lanRestartBtn" class="mt-2 px-4 py-2 bg-active-surface text-on-surface text-xs font-black rounded-xl border border-outline uppercase">Apply &amp; Restart Server</button>
                                <script>
                                    (() => {
                                        const btn = document.getElementById('lanRestartBtn');
                                        if (!btn) return;
                                        if (!window.macaronPos) { btn.remove(); return; } // shell-only control; tablet browsers must not see it
                                        btn.addEventListener('click', async () => {
                                            const form = btn.closest('form');
                                            btn.disabled = true; btn.textContent = 'Restarting...';
                                            // Save the full form first (the same POST as the Save button) so the DB and the
                                            // desktop.json handoff can never disagree with what the restart applies.
                                            try {
                                                const res = await fetch(form.action, {
                                                    method: 'POST',
                                                    body: new FormData(form),
                                                    credentials: 'same-origin',
                                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                                                });
                                                if (!res.ok) throw new Error(`save failed (${res.status})`);
                                            } catch (e) { btn.textContent = 'Save failed - retry'; btn.disabled = false; return; }
                                            // Two inputs share this name (hidden 0 + checkbox); select the checkbox by type.
                                            const enabled = document.querySelector('input[name="lan_access_enabled"][type="checkbox"]').checked;
                                            // The page reloads on success; only a rejection before the reload is a real failure.
                                            try { await window.macaronPos.restartServer(enabled); } catch (e) { btn.textContent = 'Restart failed - retry'; btn.disabled = false; }
                                        });
                                    })();
                                </script>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Receipt Printer IP</label>
                            <input type="text" name="cashier_printer_ip" value="{{ old('cashier_printer_ip', $config->cashier_printer_ip) }}" placeholder="e.g. 192.168.1.201" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Receipt Printer Windows Share</label>
                            <input type="text" name="cashier_printer_name" value="{{ old('cashier_printer_name', $config->cashier_printer_name) }}" placeholder="e.g. RECEIPT-PRINTER" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none transition-all text-white">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full h-16 bg-primary text-on-primary font-black text-sm uppercase tracking-[0.3em] rounded-2xl shadow-2xl shadow-primary/20 hover:brightness-110 active:scale-[0.98] transition-all">
                    Save Hardware Config
                </button>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <form action="{{ route('settings.test-printer') }}" method="POST">
                    @csrf
                    <input type="hidden" name="target" value="cashier">
                    <button class="w-full min-h-12 px-4 py-3 bg-active-surface text-on-surface text-xs font-black rounded-xl border border-outline uppercase">Test Receipt Printer</button>
                </form>
                <form action="{{ route('settings.test-cash-drawer') }}" method="POST">
                    @csrf
                    <button class="w-full min-h-12 px-4 py-3 bg-active-surface text-on-surface text-xs font-black rounded-xl border border-outline uppercase">Test Cash Drawer</button>
                </form>
            </div>

            <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl flex items-center justify-between">
                <div>
                    <h3 class="text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-1">Database Backup</h3>
                    <p class="text-xs text-on-surface-variant">Download an offline copy before updates or maintenance.</p>
                </div>
                <form action="{{ route('settings.backup') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-6 py-3 bg-active-surface text-on-surface text-xs font-black rounded-xl border border-outline hover:bg-outline transition-all uppercase tracking-widest">
                        Download Backup
                    </button>
                </form>
            </div>

            <div class="bg-red-500/10 border border-red-500/20 rounded-2xl p-6 shadow-xl flex items-center justify-between">
                <div>
                    <h3 class="text-red-500 text-[10px] font-black uppercase tracking-[0.2em] mb-1">Clean System (Factory Reset)</h3>
                    <p class="text-xs text-red-400">Wipe all data and return to the Initial Setup Wizard for testing personas.</p>
                </div>
                <form action="{{ route('system.reset') }}" method="POST" class="flex gap-2" onsubmit="return confirm('This permanently deletes operational data. Continue?');">
                    @csrf
                    <input type="password" name="password" required placeholder="Admin password" class="w-36 bg-background border border-red-500/30 rounded-xl px-3 text-xs text-white">
                    <input type="text" name="confirmation" required placeholder="Type RESET" class="w-28 bg-background border border-red-500/30 rounded-xl px-3 text-xs text-white">
                    <button type="submit" class="px-6 py-3 bg-red-500 text-white text-xs font-black rounded-xl hover:bg-red-600 transition-all uppercase tracking-widest shadow-lg shadow-red-500/20">
                        Reset System
                    </button>
                </form>
            </div>
        </div>

        <!-- Tab 3: Staff Management -->
        <div x-show="activeTab === 'staff'" style="display: none;" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Add User Form -->
                <div class="lg:col-span-1 bg-surface border border-outline rounded-2xl p-6 shadow-xl h-fit">
                    <h3 class="text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">Add New Staff</h3>
                    
                    <form action="{{ route('users.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Full Name</label>
                            <input type="text" name="name" required placeholder="e.g. Aslam Khan" class="w-full bg-background border border-outline rounded-xl h-10 px-4 text-xs focus:ring-1 focus:ring-primary outline-none text-white">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Email Address</label>
                            <input type="email" name="email" required placeholder="e.g. aslam@macaron.com" class="w-full bg-background border border-outline rounded-xl h-10 px-4 text-xs focus:ring-1 focus:ring-primary outline-none text-white">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Access Password</label>
                            <input type="password" name="password" required placeholder="••••••••" class="w-full bg-background border border-outline rounded-xl h-10 px-4 text-xs focus:ring-1 focus:ring-primary outline-none text-white">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Role</label>
                            <select name="role" required class="w-full bg-background border border-outline rounded-xl h-10 px-4 text-xs focus:ring-1 focus:ring-primary outline-none text-white">
                                <option value="cashier" selected>Cashier</option>
                                <option value="admin">Owner (Full Access)</option>
                                <option value="manager">Manager (Optional)</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full h-12 bg-primary text-on-primary font-black text-xs uppercase tracking-widest rounded-xl hover:brightness-110 transition-all mt-4">
                            Register Staff
                        </button>
                    </form>
                </div>

                <!-- Users List -->
                <div class="lg:col-span-2 bg-surface border border-outline rounded-2xl overflow-hidden shadow-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-active-surface/50 border-b border-outline">
                                <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Name / Email</th>
                                <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-center">Role</th>
                                <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline/5">
                            @foreach($users as $user)
                            <tr class="hover:bg-active-surface/20 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-white">{{ $user->name }}</span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $user->email }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-[9px] font-black px-2 py-0.5 rounded uppercase {{ $user->role === 'admin' ? 'bg-primary/20 text-primary' : ($user->role === 'manager' ? 'bg-blue-500/20 text-blue-400' : 'bg-on-surface-variant/20 text-on-surface-variant') }}">
                                        {{ $user->role === 'admin' ? 'owner' : $user->role }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if(auth()->check() && $user->id !== auth()->id())
                                    <form action="{{ route('users.delete', $user->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this staff user?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-error/60 hover:text-error hover:bg-error/10 rounded-lg transition-colors">
                                            <span class="material-symbols-rounded text-sm">delete</span>
                                        </button>
                                    </form>
                                    @else
                                    <span class="text-[9px] font-black text-on-surface-variant/55 italic">Logged In</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
