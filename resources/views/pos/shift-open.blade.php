@extends('layouts.app')

@section('content')
<div class="flex-1 flex items-center justify-center p-8 bg-background">
    <div class="w-full max-w-md bg-surface border border-outline rounded-3xl p-10 shadow-2xl">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-primary/10 rounded-3xl flex items-center justify-center mx-auto mb-6">
                <span class="material-symbols-rounded text-primary text-4xl">key</span>
            </div>
            <h1 class="text-3xl font-black text-white uppercase tracking-tighter mb-2">Open New Shift</h1>
            <p class="text-on-surface-variant text-xs font-bold uppercase tracking-widest">Enter opening register balance to continue</p>
        </div>

        <form action="{{ route('shift.store') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label class="block text-[10px] font-black text-on-surface-variant uppercase tracking-[0.2em] mb-3">Opening Cash (Rs.)</label>
                <div class="relative group">
                    <span class="absolute left-5 top-1/2 -translate-y-1/2 text-primary font-black text-lg">Rs.</span>
                    <input 
                        type="number" 
                        name="opening_balance" 
                        required 
                        step="0.01" 
                        placeholder="5,000.00"
                        class="w-full bg-active-surface border-2 border-outline rounded-2xl h-16 pl-14 pr-6 text-2xl font-black text-white focus:border-primary focus:ring-0 transition-all placeholder:text-on-surface-variant/20"
                    >
                </div>
                @error('opening_balance')
                    <p class="text-error text-[10px] font-bold mt-2 uppercase tracking-widest">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full h-16 bg-primary text-on-primary rounded-2xl font-black text-lg uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all shadow-xl shadow-primary/20 flex items-center justify-center gap-4">
                <span>Start Session</span>
                <span class="material-symbols-rounded">arrow_forward</span>
            </button>
        </form>

        <div class="mt-8 pt-8 border-t border-outline/50 flex items-center justify-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
            <span class="text-[9px] font-black text-on-surface-variant uppercase tracking-[0.2em]">Terminal Restricted Access</span>
        </div>
    </div>
</div>
@endsection
