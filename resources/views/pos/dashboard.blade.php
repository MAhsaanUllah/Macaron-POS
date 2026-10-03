@extends('layouts.app')

@section('content')

@if(auth()->check() && in_array(auth()->user()->role, ['admin', 'manager', 'cashier']))
<div class="flex-1 flex overflow-hidden flex-row pos-engine-workspace">
    <!-- Products Grid (Flexible 65%) -->
    <section class="flex-1 flex flex-col p-4 md:p-6 overflow-y-auto w-[60%] lg:w-[65%]">
        <!-- Tag Pills Navigation -->
        <div class="flex gap-2 pb-4 overflow-x-auto no-scrollbar flex-shrink-0">
            <button 
                @click="selectedCategory = 'all'"
                :class="selectedCategory === 'all' ? 'bg-primary text-on-primary shadow-lg shadow-primary/20' : 'bg-active-surface text-on-surface-variant border border-outline'"
                class="tag-pill min-h-12 px-5 font-bold rounded-full whitespace-nowrap text-xs transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >All Items</button>
            @foreach($categories as $category)
            <button 
                @click="selectedCategory = '{{ $category->slug }}'"
                :class="selectedCategory === '{{ $category->slug }}' ? 'bg-primary text-on-primary shadow-lg shadow-primary/20' : 'bg-active-surface text-on-surface-variant border border-outline'"
                class="tag-pill min-h-12 px-5 font-bold rounded-full whitespace-nowrap text-xs transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >{{ $category->name }}</button>
            @endforeach
        </div>

        <!-- Bento Grid Food Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <template x-for="product in products" :key="product.id">
                <button type="button"
                    x-show="(selectedCategory === 'all' || product.category.slug === selectedCategory) && (product.name.toLowerCase().includes(searchQuery.toLowerCase()) || (product.barcode || '').toLowerCase().includes(searchQuery.toLowerCase()))"
                    @click="addItem(product)"
                    :disabled="!product.is_saleable"
                    :class="product.is_saleable ? 'hover:border-primary cursor-pointer' : 'opacity-50 cursor-not-allowed'"
                    class="group relative bg-active-surface/50 border border-outline rounded-xl overflow-hidden focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary transition-colors flex flex-col text-left"
                >
                    <div class="product-card-img aspect-video w-full overflow-hidden bg-gradient-to-br from-primary/20 via-active-surface to-surface relative flex items-center justify-center">
                        <img x-show="product.image_path" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.03]" :src="product.image_path ? (product.image_path.startsWith('http') ? product.image_path : '/' + product.image_path) : ''" :alt="product.name"/>
                        <span x-show="!product.image_path" class="text-4xl font-black text-primary/70" x-text="product.name.charAt(0)"></span>
                    </div>
                    <div class="p-3 flex-1 flex flex-col justify-between bg-surface/50">
                        <div class="flex justify-between items-start gap-2 mb-1">
                            <h3 class="font-bold text-on-surface text-[11px] leading-tight line-clamp-1" x-text="product.name"></h3>
                            <span class="font-label-numeric text-primary text-[11px] font-black" x-text="formatCurrency(product.price)"></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-1 h-1 rounded-full" :class="product.is_saleable ? 'bg-primary' : 'bg-error'"></div>
                            <span class="text-[8px] uppercase tracking-widest font-black" :class="product.is_saleable ? 'text-primary' : 'text-error'" x-text="product.stock_status"></span>
                        </div>
                    </div>
                </button>
            </template>
        </div>
    </section>

    <!-- Sidebar Order (Flexible 35%) -->
    <section class="w-[40%] lg:w-[35%] bg-surface border-l border-outline flex flex-col h-full min-h-0 flex-shrink-0 shadow-2xl transition-all">
        <!-- Order Header -->
        <div class="bg-active-surface/50 px-3 py-2 order-header-compact flex-shrink-0 border-b border-outline">
            <div class="flex justify-between items-center mb-2">
                <div>
                    <h2 class="font-display-lg text-base font-black tracking-tight text-white leading-none">Current sale <span class="text-primary" x-text="'(' + cart.length + ')'">(0)</span></h2>
                    <p class="text-on-surface-variant text-[7px] uppercase tracking-widest mt-1 font-bold">Counter 01 • {{ auth()->user()->name }}</p>
                </div>
                <div class="flex bg-background p-0.5 rounded-lg border border-outline">
                    <button 
                        @click="orderType = 'COUNTER'"
                        :class="orderType === 'COUNTER' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant'"
                        class="min-h-10 px-3 rounded-full text-xs font-bold transition-colors"
                    >Counter</button>
                    @if($config->pickup_enabled)
                    <button
                        @click="orderType = 'PICKUP'"
                        :class="orderType === 'PICKUP' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant'"
                        class="min-h-10 px-3 rounded-full text-xs font-bold transition-colors"
                    >Pickup / WhatsApp</button>
                    @endif
                    @if($config->delivery_enabled)
                    <button
                        @click="orderType = 'DELIVERY'"
                        :class="orderType === 'DELIVERY' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant'"
                        class="min-h-10 px-3 rounded-full text-xs font-bold transition-colors"
                    >Delivery</button>
                    @endif
                </div>
            </div>

            <!-- Customer Info with Loyalty -->
            <div class="space-y-1.5">
                <div class="grid grid-cols-2 gap-2">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-rounded text-on-surface-variant text-base">person</span>
                        <input
                            type="text"
                            x-model="customerName"
                            placeholder="Customer name"
                            class="w-full bg-background border border-outline rounded-xl h-10 pl-10 pr-3 text-sm text-on-surface focus:border-primary focus:ring-0"
                        >
                    </div>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-rounded text-on-surface-variant text-base">call</span>
                        <input
                            type="tel"
                            x-model="customerPhone"
                            @input.debounce.500ms="searchCustomer(customerPhone)"
                            placeholder="Phone"
                            class="w-full bg-background border border-outline rounded-xl h-10 pl-10 pr-3 text-sm text-on-surface focus:border-primary focus:ring-0"
                        >
                    </div>
                </div>

                <template x-if="selectedCustomer">
                    <div class="bg-primary/5 border border-primary/20 rounded-lg px-2 py-1.5 flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-[9px] font-black text-primary uppercase tracking-widest truncate" x-text="selectedCustomer.name"></p>
                            <p class="text-[7px] font-bold text-on-surface-variant">Points: <span class="text-primary font-black" x-text="selectedCustomer.total_points_balance"></span></p>
                        </div>
                        <button @click="selectedCustomer = null; pointsRedeemed = 0; customerName = ''" class="text-error/50 hover:text-error transition-colors ml-1">
                            <span class="material-symbols-rounded text-xs">close</span>
                        </button>
                    </div>
                </template>

                <template x-if="searchingCustomer">
                    <p class="text-[7px] text-on-surface-variant text-center font-bold uppercase tracking-widest">Searching...</p>
                </template>

                <template x-if="selectedCustomer && parseFloat(selectedCustomer.total_points_balance) > 0">
                    <div class="relative group">
                        <span class="absolute left-2 top-1/2 -translate-y-1/2 material-symbols-rounded text-primary text-xs">redeem</span>
                        <input 
                            type="number" 
                            x-model.number="pointsRedeemed"
                            :max="selectedCustomer.total_points_balance"
                            placeholder="Redeem Points"
                            class="w-full bg-background border border-outline rounded-lg h-8 pl-7 pr-2 text-[9px] font-bold text-white focus:border-primary focus:ring-0 transition-all placeholder:text-on-surface-variant/30"
                        >
                    </div>
                </template>
            </div>
        </div>

        <!-- Scrollable Order Items -->
        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain order-item-list" aria-label="Current sale items">
            <div x-show="cart.length === 0" class="h-full min-h-32 flex flex-col items-center justify-center text-on-surface-variant/60">
                <span class="material-symbols-rounded text-4xl">shopping_cart</span>
                <p class="mt-2 text-sm font-bold">Tap a product to start</p>
            </div>
            <template x-for="item in cart" :key="item.id">
                <div class="zebra-item flex items-center gap-2 px-3 py-2 group transition-colors border-b border-outline/10">
                    <div class="w-9 h-9 rounded-lg overflow-hidden bg-active-surface flex-shrink-0 border border-outline relative">
                        <img x-show="item.image" class="w-full h-full object-cover" :src="item.image?.startsWith('http') ? item.image : '/' + item.image" :alt="item.name"/>
                        <span x-show="!item.image" class="w-full h-full flex items-center justify-center font-bold text-primary" x-text="item.name.charAt(0)"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-on-surface text-[11px] truncate" x-text="item.name"></h4>
                        <p class="text-[9px] text-primary font-black uppercase tracking-widest mt-0.5" x-text="formatCurrency(item.price) + ' / ' + (item.uom === 'KG' ? 'kg' : 'pc')"></p>
                    </div>
                    <div x-show="item.uom !== 'KG' && item.uom !== 'Gram'" class="flex items-center flex-shrink-0 bg-background/50 rounded-lg border border-outline">
                        <button @click="updateQuantity(item.id, -1)" class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-active-surface text-on-surface-variant transition-colors" :aria-label="'Remove one ' + item.name">
                            <span class="material-symbols-rounded text-sm">remove</span>
                        </button>
                        <span class="font-label-numeric w-4 text-center text-[10px] font-black" x-text="item.quantity"></span>
                        <button @click="updateQuantity(item.id, 1)" class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-active-surface text-on-surface-variant transition-colors" :aria-label="'Add one ' + item.name">
                            <span class="material-symbols-rounded text-sm">add</span>
                        </button>
                    </div>
                    <input x-show="item.uom === 'KG' || item.uom === 'Gram'" type="number" min="0.001" step="0.001" x-model.number="item.quantity" class="w-20 h-10 bg-background border border-outline rounded-xl px-2 text-sm font-bold text-on-surface" aria-label="Weight in kilograms">
                    <div class="text-right w-16 flex-shrink-0">
                        <p class="font-label-numeric text-[11px] font-black" x-text="formatCurrency(item.price * item.quantity)"></p>
                        <button @click="removeItem(item.id)" class="text-error/50 hover:text-error transition-colors">
                            <span class="material-symbols-rounded text-xs">delete</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Sticky Checkout Section -->
        <div class="bg-surface border-t border-outline p-3 order-totals-compact flex-shrink-0 shadow-[0_-8px_24px_rgba(0,0,0,0.18)]">
            <div class="grid grid-cols-2 gap-3 mb-2 text-xs">
                <div class="flex justify-between items-center">
                    <span class="font-bold text-on-surface-variant">Subtotal</span>
                    <span class="font-label-numeric font-bold" x-text="formatCurrency(subtotal)"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-bold text-on-surface-variant">Tax {{ number_format((float) ($config->tax_rate ?? 0), 2) }}%</span>
                    <span class="font-label-numeric font-bold" x-text="formatCurrency(taxTotal)"></span>
                </div>
                <template x-if="selectedCustomer && parseFloat(pointsRedeemed) > 0">
                    <div class="flex justify-between items-center">
                        <span class="text-[8px] font-black text-on-surface-variant uppercase tracking-widest">Points</span>
                        <span class="font-label-numeric text-[10px] font-bold text-error" x-text="'-' + formatCurrency(pointsRedeemed)"></span>
                    </div>
                </template>
            </div>
            
            <div class="flex justify-between items-center py-2 border-y border-dashed border-outline">
                <span class="font-black text-on-surface uppercase tracking-widest">Total payable</span>
                <span class="text-xl font-black font-display-lg leading-none tracking-tighter" :class="effectiveTotal < grandTotal ? 'text-error' : 'text-primary'" x-text="formatCurrency(effectiveTotal)"></span>
            </div>

            <!-- Payment Method Splitter -->
            <div class="mt-2 space-y-2">
                <div class="flex bg-background p-0.5 rounded-lg border border-outline">
                    <button 
                        @click="paymentMethod = 'cash'"
                        :class="paymentMethod === 'cash' ? 'bg-primary text-on-primary shadow-lg shadow-primary/20' : 'text-on-surface-variant'"
                        class="flex-1 h-10 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-1"
                    >
                        <span class="material-symbols-rounded text-xs">payments</span>
                        Cash
                    </button>
                    @if($config->card_enabled)
                    <button 
                        @click="paymentMethod = 'card'"
                        :class="paymentMethod === 'card' ? 'bg-primary text-on-primary shadow-lg shadow-primary/20' : 'text-on-surface-variant'"
                        class="flex-1 h-10 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-1"
                    >
                        <span class="material-symbols-rounded text-xs">credit_card</span>
                        Card / Tap
                    </button>
                    @endif
                    @if($config->raast_enabled && $config->raast_qr_path)
                    <button 
                        @click="paymentMethod = 'qr_digital'"
                        :class="paymentMethod === 'qr_digital' ? 'bg-primary text-on-primary shadow-lg shadow-primary/20' : 'text-on-surface-variant'"
                        class="flex-1 h-10 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-1"
                    >
                        <span class="material-symbols-rounded text-xs">qr_code_2</span>
                        Raast QR
                    </button>
                    @endif
                </div>

                <!-- Cash Inputs -->
                <div x-show="paymentMethod === 'cash'" x-transition class="grid grid-cols-[1fr_auto] gap-2 items-end">
                    <div class="flex items-center justify-between">
                        <div class="w-full">
                            <label for="cash-received" class="block text-xs font-bold text-on-surface mb-1">Cash received</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-primary font-black text-xs">{{ $settings->currency_symbol ?? 'Rs.' }}</span>
                                <input id="cash-received" type="number" x-model.number="amountTendered" min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="w-full bg-background border-2 border-outline rounded-xl h-12 pl-12 pr-3 text-xl font-black text-white focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="amountTendered = effectiveTotal" class="h-12 px-3 rounded-xl bg-active-surface text-xs font-bold hover:bg-primary/15">Exact</button>
                    <div class="col-span-2 flex justify-between items-center min-h-10 px-3 rounded-xl" :class="cashShortfall > 0 ? 'bg-error/10' : 'bg-primary/10'">
                        <span class="text-sm font-bold" :class="cashShortfall > 0 ? 'text-error' : 'text-primary'" x-text="cashShortfall > 0 ? 'Still due' : 'Change due'"></span>
                        <span class="text-lg font-black" :class="cashShortfall > 0 ? 'text-error' : 'text-primary'" x-text="formatCurrency(cashShortfall > 0 ? cashShortfall : cashChange)"></span>
                    </div>
                </div>

                <div x-show="paymentMethod === 'qr_digital'" x-transition class="p-3 bg-white rounded-xl text-center">
                    @if($config->raast_qr_path)
                        <img src="{{ asset('storage/'.$config->raast_qr_path) }}" alt="Raast payment QR" class="w-36 h-36 mx-auto object-contain">
                    @endif
                    <p class="text-black text-xs font-bold mt-2">Customer pays the exact total. Verify the merchant bank app/SMS—not a customer screenshot—then enter the reference.</p>
                </div>

                <div x-show="paymentMethod === 'card'" class="text-xs text-on-surface-variant px-1">
                    Enter {{ $settings->currency_symbol ?? 'Rs.' }} <span x-text="effectiveTotal.toFixed(2)"></span> on {{ $config->card_terminal_name ?: 'the bank terminal' }}. Customer inserts, swipes or taps. Wait for <strong>Approved</strong>, then enter the RRN below.
                </div>

                <!-- Card/Raast Inputs -->
                <div x-show="paymentMethod !== 'cash'" x-transition>
                    <div class="relative group">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-rounded text-primary text-xs">confirmation_number</span>
                        <input 
                            type="text" 
                            x-model="transactionReference"
                            placeholder="Required bank reference / RRN"
                            class="w-full bg-background border border-outline rounded-lg h-8 pl-9 pr-2 text-[10px] font-black text-white focus:border-primary focus:ring-0 transition-all placeholder:text-on-surface-variant/30"
                        >
                    </div>
                </div>
            </div>

            <button 
                @click="completeOrder()"
                :disabled="!canCompletePayment || isSubmitting"
                :class="(!canCompletePayment || isSubmitting) ? 'opacity-40 grayscale cursor-not-allowed' : 'hover:brightness-110 active:scale-[0.98] shadow-2xl shadow-primary/30 bg-primary text-on-primary'"
                class="w-full mt-2 h-12 rounded-xl font-black text-xs uppercase tracking-[0.1em] flex items-center justify-center gap-2 transition-all"
            >
                <span class="material-symbols-rounded text-xl">payments</span>
                <span x-text="isSubmitting ? 'Saving sale…' : 'Complete Payment'"></span>
            </button>
        </div>
    </section>
</div>

<div
    x-show="weightProduct"
    x-cloak
    @keydown.escape.window="weightProduct = null"
    class="fixed inset-0 z-[100] bg-black/60 flex items-center justify-center p-6"
>
    <div class="w-full max-w-lg bg-surface border border-outline rounded-[28px] p-6 shadow-2xl" @click.outside="weightProduct = null">
        <div class="flex items-start justify-between mb-6">
            <div>
                <p class="text-sm text-on-surface-variant mb-1">Select sale weight</p>
                <h2 class="text-2xl font-bold text-on-surface" x-text="weightProduct?.name"></h2>
            </div>
            <button type="button" @click="weightProduct = null" class="w-12 h-12 rounded-full hover:bg-active-surface flex items-center justify-center" aria-label="Close weight selector">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-6">
            <button type="button" @click="selectWeight(0.25)" class="h-16 rounded-2xl bg-active-surface border border-outline hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary font-bold">250 g</button>
            <button type="button" @click="selectWeight(0.5)" class="h-16 rounded-2xl bg-active-surface border border-outline hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary font-bold">500 g</button>
            <button type="button" @click="selectWeight(1)" class="h-16 rounded-2xl bg-primary text-on-primary font-bold">1 kg</button>
            <button type="button" @click="selectWeight(2)" class="h-16 rounded-2xl bg-active-surface border border-outline hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary font-bold">2 kg</button>
            <button type="button" @click="selectWeight(5)" class="h-16 rounded-2xl bg-active-surface border border-outline hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary font-bold">5 kg</button>
            <button type="button" @click="$refs.customWeight.focus()" class="h-16 rounded-2xl bg-active-surface border border-outline hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary font-bold">Custom</button>
        </div>

        <label class="block text-sm font-medium text-on-surface-variant mb-2" for="custom-weight">Custom weight in kilograms</label>
        <div class="flex gap-3">
            <input id="custom-weight" x-ref="customWeight" type="number" min="0.001" step="0.001" x-model.number="customWeight" class="flex-1 h-14 bg-background border border-outline rounded-xl px-4 text-lg text-on-surface focus:border-primary focus:ring-0" placeholder="e.g. 1.750">
            <button type="button" @click="selectWeight(customWeight)" :disabled="!customWeight || customWeight <= 0" class="h-14 px-6 rounded-full bg-primary text-on-primary font-bold disabled:opacity-40">Add</button>
        </div>
    </div>
</div>
@endif
@endsection
