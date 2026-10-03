@extends('layouts.app')

@section('content')
<div class="flex-1 flex items-center justify-center p-8 bg-background">
    <div class="w-full max-w-2xl bg-surface border border-outline rounded-3xl p-10 shadow-2xl">
        <div class="text-center mb-10">
            <div class="w-20 h-20 bg-error/10 rounded-3xl flex items-center justify-center mx-auto mb-6">
                <span class="material-symbols-rounded text-error text-4xl">lock</span>
            </div>
            <h1 class="text-3xl font-black text-white uppercase tracking-tighter mb-2">End Session (Z-Report)</h1>
            <p class="text-on-surface-variant text-xs font-bold uppercase tracking-widest">Verify cash drawer and close shift</p>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-10">
            <div class="p-6 bg-active-surface/50 rounded-2xl border border-outline">
                <p class="text-[9px] font-black text-on-surface-variant uppercase tracking-widest mb-1">Opening Balance</p>
                <p class="text-2xl font-black text-white">Rs. {{ number_format($activeShift->opening_balance, 2) }}</p>
            </div>
            <div class="p-6 bg-primary/5 rounded-2xl border border-primary/20">
                <p class="text-[9px] font-black text-primary uppercase tracking-widest mb-1">Expected Cash Sales</p>
                <p class="text-2xl font-black text-primary">Rs. {{ number_format($activeShift->expected_cash, 2) }}</p>
            </div>
        </div>

        <form id="closeShiftForm" action="{{ route('shift.close.store') }}" method="POST" class="space-y-8" onsubmit="event.preventDefault(); handleShiftClosing();">
            @csrf
            <div>
                <label class="block text-[10px] font-black text-on-surface-variant uppercase tracking-[0.2em] mb-4">Actual Physical Cash Collected</label>
                <div class="relative">
                    <span class="absolute left-6 top-1/2 -translate-y-1/2 text-primary font-black text-xl">Rs.</span>
                    <input 
                        id="declaredCash"
                        type="number" 
                        name="declared_cash" 
                        required 
                        step="0.01" 
                        placeholder="Enter total cash in drawer"
                        class="w-full bg-active-surface border-2 border-outline rounded-2xl h-20 pl-16 pr-8 text-3xl font-black text-white focus:border-primary focus:ring-0 transition-all"
                    >
                </div>
                @error('declared_cash')
                    <p class="text-error text-[10px] font-bold mt-2 uppercase tracking-widest">{{ $message }}</p>
                @enderror
            </div>

            <div class="bg-active-surface/30 p-6 rounded-2xl border border-outline border-dashed">
                <div class="flex items-center gap-4 text-on-surface-variant">
                    <span class="material-symbols-rounded">info</span>
                    <p class="text-[10px] font-bold leading-relaxed uppercase tracking-wider">
                        By closing the shift, you are confirming that the cash mentioned above has been counted and verified. A Z-Report will be automatically printed for audit.
                    </p>
                </div>
            </div>

            <button type="submit" class="w-full h-20 bg-error text-on-primary rounded-2xl font-black text-xl uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all shadow-xl shadow-error/20 flex items-center justify-center gap-4">
                <span>Finalize & Close Shift</span>
                <span class="material-symbols-rounded">check_circle</span>
            </button>
        </form>
    </div>
</div>

<script>
    function handleShiftClosing() {
        const declared = parseFloat(document.getElementById('declaredCash').value);
        if (isNaN(declared)) return;

        const shiftData = {
            user: @json(auth()->user()->name),
            start: new Date(@json($activeShift->start_time->format('c'))).toLocaleString(),
            end: new Date().toLocaleString(),
            opening: {{ (float)$activeShift->opening_balance }},
            expected: {{ (float)$activeShift->expected_cash }},
            declared: declared
        };

        // Print Z-Report
        printZReport(shiftData);

        // Submit form after small delay
        setTimeout(() => {
            document.getElementById('closeShiftForm').submit();
        }, 500);
    }
</script>
@endsection
