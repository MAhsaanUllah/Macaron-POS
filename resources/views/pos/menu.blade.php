@extends('layouts.app')

@section('content')
<div class="flex-1 p-8 overflow-y-auto" x-data="{ showItemModal: false, showCategoryModal: false }">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div><h1 class="text-3xl font-black text-white uppercase tracking-tighter">Products & Add-ons</h1><p class="text-sm text-on-surface-variant mt-1">Boxes, gift wrap and delivery charges should be products so they appear on stock, receipt and tax records.</p></div>
            <div class="flex gap-3">
                <button @click="showItemModal = true" class="px-6 py-2.5 bg-primary text-on-primary text-[10px] font-black rounded-xl shadow-lg shadow-primary/20 hover:brightness-110 transition-all uppercase tracking-widest">+ Add Item</button>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-primary/10 border border-primary/20 text-primary rounded-xl text-xs font-bold uppercase tracking-wider">
                {{ session('success') }}
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

        <details class="mb-6 bg-surface border border-outline rounded-2xl p-5">
            <summary class="cursor-pointer font-bold text-on-surface">Bulk import ready-made, grocery or shop-made products</summary>
            <p class="text-sm text-on-surface-variant mt-3">Download the CSV, fill it in Excel, then upload once. Re-importing the same barcode updates that product instead of duplicating it.</p>
            <div class="flex flex-col md:flex-row gap-3 mt-4">
                <a href="{{ route('items.template') }}" class="min-h-12 px-5 rounded-xl border border-outline flex items-center justify-center text-sm font-bold">Download CSV template</a>
                <form action="{{ route('items.import') }}" method="POST" enctype="multipart/form-data" class="flex-1 flex flex-col md:flex-row gap-3">
                    @csrf
                    <input type="file" name="catalog" required accept=".csv,text/csv" class="flex-1 min-h-12 bg-background border border-outline rounded-xl p-2 text-sm text-on-surface-variant">
                    <button class="min-h-12 px-6 rounded-xl bg-primary text-on-primary font-bold">Import catalog</button>
                </form>
            </div>
            <p class="text-xs text-on-surface-variant mt-3"><strong>stock_type:</strong> ready_made for branded/resale FMCG; made_here for mithai/bakery items that need daily batches. Use uom kg or pcs.</p>
        </details>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Categories Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-surface border border-outline rounded-2xl p-6 shadow-xl">
                    <h3 class="text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">Categories</h3>
                    <div class="space-y-2">
                        @foreach($categories as $category)
                        <div class="flex items-center justify-between p-3 rounded-xl bg-active-surface/30 border border-outline hover:border-primary/30 transition-all group cursor-pointer">
                            <span class="text-xs font-bold text-on-surface">{{ $category->name }}</span>
                            <span class="text-[10px] font-black text-on-surface-variant bg-background px-2 py-0.5 rounded-md">{{ $category->items_count }} Items</span>
                        </div>
                        @endforeach
                    </div>
                    <button @click="showCategoryModal = true" class="w-full mt-6 py-3 border border-dashed border-outline rounded-xl text-[10px] font-black text-on-surface-variant uppercase tracking-widest hover:border-primary hover:text-primary transition-all">
                        + New Category
                    </button>
                </div>
            </div>

            <!-- Items List -->
            <div class="lg:col-span-2">
                <div class="bg-surface border border-outline rounded-2xl overflow-hidden shadow-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-active-surface/50 border-b border-outline">
                                <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Item</th>
                                <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-center">Category</th>
                                <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-center">Stock Type</th>
                                <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-right">Price</th>
                                <th class="px-6 py-4 text-[10px] font-black text-on-surface-variant uppercase tracking-widest text-right">Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline/5">
                            @foreach($items as $item)
                            <tr class="hover:bg-active-surface/20 transition-colors">
                                <td class="px-6 py-4">
                                        @if($item->image_path)
                                            <img src="{{ asset($item->image_path) }}" class="w-10 h-10 rounded-lg object-cover border border-outline">
                                        @else
                                            <div class="w-10 h-10 rounded-lg bg-surface border border-outline flex items-center justify-center font-black text-sm text-primary">
                                                {{ strtoupper(substr($item->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <span>
                                            <span class="block text-xs font-bold text-white">{{ $item->name }}</span>
                                            @if(!$item->tracks_batches && $item->expiry_date)
                                                <span class="block text-[10px] {{ $item->expiry_date->lte(today()) ? 'text-error font-bold' : 'text-on-surface-variant' }}">
                                                    {{ $item->expiry_date->lte(today()) ? 'Expired' : 'Nearest expiry' }}: {{ $item->expiry_date->format('d M Y') }}
                                                </span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-[10px] font-black text-on-surface-variant bg-active-surface px-2 py-0.5 rounded uppercase">{{ $item->category->name }}</span>
                                </td>
                                <td class="px-6 py-4 text-center text-xs font-bold">{{ $item->tracks_batches ? 'Made here' : 'Ready-made' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-label-numeric text-xs font-black text-primary">{{ $settings->currency_symbol }} {{ number_format($item->price, 2) }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-[10px] font-black {{ $item->stock_qty > 0 ? 'text-primary' : 'text-error' }}">{{ number_format((float) $item->stock_qty, $item->uom === 'KG' ? 3 : 0) }} {{ $item->uom === 'KG' ? 'kg' : 'pcs' }}</span>
                                    @if(!$item->tracks_batches && (float) $item->stock_qty > 0)
                                        <details class="mt-2 text-left">
                                            <summary class="cursor-pointer text-[10px] font-bold text-error">Expire / damage / shortage</summary>
                                            <form method="POST" action="{{ route('items.loss.store', $item) }}" class="mt-2 space-y-2 min-w-48">
                                                @csrf
                                                <input type="number" name="quantity" required min="0.001" max="{{ $item->stock_qty }}" step="0.001" placeholder="Quantity" class="w-full h-9 rounded-lg bg-background border-outline text-on-surface text-xs">
                                                <select name="reason" required class="w-full h-9 rounded-lg bg-background border-outline text-on-surface text-xs">
                                                    <option value="expired">Expired</option>
                                                    <option value="damaged">Damaged</option>
                                                    <option value="shortage">Count shortage</option>
                                                    <option value="customer_return">Bad customer return</option>
                                                </select>
                                                <input type="text" name="notes" maxlength="255" placeholder="Optional note" class="w-full h-9 rounded-lg bg-background border-outline text-on-surface text-xs">
                                                <button class="w-full h-9 rounded-lg bg-error text-white text-xs font-bold">Remove from stock</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    {{ $items->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Category Creation Modal -->
    <div x-show="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
        <div @click.away="showCategoryModal = false" class="w-full max-w-md bg-surface border border-outline rounded-3xl p-8 shadow-2xl space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-black text-white uppercase tracking-tight">New Category</h3>
                <button @click="showCategoryModal = false" class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <form action="{{ route('categories.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Category Name</label>
                    <input type="text" name="name" required placeholder="e.g. Desserts" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none text-white">
                </div>
                <button type="submit" class="w-full h-12 bg-primary text-on-primary font-black text-xs uppercase tracking-widest rounded-xl hover:brightness-110 transition-all">Create Category</button>
            </form>
        </div>
    </div>

    <!-- Item Creation Modal -->
    <div x-show="showItemModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
        <div @click.away="showItemModal = false" class="w-full max-w-lg bg-surface border border-outline rounded-3xl p-8 shadow-2xl space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-black text-white uppercase tracking-tight">New Sweet Product</h3>
                <button @click="showItemModal = false" class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2 col-span-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Item Name</label>
                        <input type="text" name="name" required placeholder="e.g. Mix Mithai or Premium Gift Box" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none text-white">
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Price</label>
                        <input type="number" name="price" required step="0.01" min="0" placeholder="0.00" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none text-white">
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Initial Stock Qty</label>
                        <input type="number" name="stock_qty" required min="0" step="0.001" placeholder="100" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none text-white">
                    </div>

                    <div class="space-y-2 col-span-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Category</label>
                        <select name="category_id" required class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none text-white">
                            <option value="" disabled selected>Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">HS Code (FBR)</label>
                        <input type="text" name="hs_code" placeholder="Confirm with accountant" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none text-white">
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Unit of Measure</label>
                        <select name="uom" required class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm focus:ring-1 focus:ring-primary outline-none text-white">
                            <option value="Numbers, pieces, units">Per piece</option>
                            <option value="KG">Per kilogram</option>
                        </select>
                    </div>

                    <input type="hidden" name="sale_type" value="Goods at standard rate (default)">

                    <label class="col-span-2 flex items-center gap-3 p-4 rounded-xl bg-active-surface cursor-pointer">
                        <input type="hidden" name="tracks_batches" value="0">
                        <input type="checkbox" name="tracks_batches" value="1" class="w-5 h-5 rounded border-outline bg-background text-primary">
                        <span><span class="block text-sm font-bold">Made in this shop</span><span class="block text-xs text-on-surface-variant">Enable daily batches for mithai, cakes and bakery production. Leave off for purchased grocery/resale items and packaging.</span></span>
                    </label>

                    <div class="space-y-2 col-span-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Nearest Expiry (Ready-made only)</label>
                        <input type="date" name="expiry_date" class="w-full bg-background border border-outline rounded-xl h-12 px-4 text-sm text-white">
                        <p class="text-xs text-on-surface-variant">Leave blank for shop-made products, packaging, or items without expiry.</p>
                    </div>

                    <div class="space-y-2 col-span-2">
                        <label class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Item Photo</label>
                        <input type="file" name="image" class="w-full bg-background border border-outline rounded-xl p-2 text-xs text-on-surface-variant file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-primary file:text-on-primary hover:file:brightness-110 cursor-pointer">
                    </div>
                </div>

                <button type="submit" class="w-full h-12 bg-primary text-on-primary font-black text-xs uppercase tracking-widest rounded-xl hover:brightness-110 transition-all">Create Menu Item</button>
            </form>
        </div>
    </div>
</div>
@endsection
