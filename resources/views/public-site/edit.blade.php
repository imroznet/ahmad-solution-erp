<x-layouts.app title="Public site">
    <h1 class="text-xl font-semibold">Public site</h1>
    <p class="mt-1 max-w-2xl text-sm text-muted">ahmad.com.bd stays visible unless maintenance is turned on. The contact form stays on unless it is turned off. This screen does not hide the ERP, and it does not change quotation share links.</p>

    <form method="POST" action="{{ route('public-site.update') }}" class="mt-6 max-w-2xl space-y-5 rounded border border-line bg-white px-4 py-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="maintenance_enabled" value="0">
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="maintenance_enabled" value="1" class="mt-1" @checked(filter_var(old('maintenance_enabled', $enabled), FILTER_VALIDATE_BOOLEAN))>
            <span>
                <span class="font-medium">Hide the public site for maintenance</span>
                <span class="mt-1 block text-muted">Visitors then see that the site is unavailable right now because of maintenance. Turn this off to show the site again.</span>
            </span>
        </label>

        <input type="hidden" name="contact_form_enabled" value="0">
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="contact_form_enabled" value="1" class="mt-1" @checked(filter_var(old('contact_form_enabled', $contactFormEnabled), FILTER_VALIDATE_BOOLEAN))>
            <span>
                <span class="font-medium">Contact form</span>
                <span class="mt-1 block text-muted">On shows the form. Off hides it and shows the phone, email, and office address. A submission is refused while it is off.</span>
            </span>
        </label>

        <input type="hidden" name="recaptcha_enabled" value="0">
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="recaptcha_enabled" value="1" class="mt-1" @checked(filter_var(old('recaptcha_enabled', $recaptchaEnabled), FILTER_VALIDATE_BOOLEAN))>
            <span>
                <span class="font-medium">reCAPTCHA on the contact form</span>
                <span class="mt-1 block text-muted">Visitors tick the “I’m not a robot” checkbox. Set <span class="font-mono">RECAPTCHA_SITE_KEY</span> and <span class="font-mono">RECAPTCHA_SECRET_KEY</span> in the environment. The keys are not stored in the database.</span>
            </span>
        </label>
        @if ((filter_var(old('recaptcha_enabled', $recaptchaEnabled), FILTER_VALIDATE_BOOLEAN)) && ! $recaptchaKeysPresent)
            <p class="text-sm text-red-800">reCAPTCHA keys missing</p>
        @endif
        @error('maintenance_enabled')
            <p class="text-sm text-red-800">{{ $message }}</p>
        @enderror
        @error('contact_form_enabled')
            <p class="text-sm text-red-800">{{ $message }}</p>
        @enderror
        @error('recaptcha_enabled')
            <p class="text-sm text-red-800">{{ $message }}</p>
        @enderror
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save</button>
    </form>

    <div class="mt-6 max-w-2xl text-sm">
        <p class="font-medium">Flag files</p>
        <p class="mt-1 break-all font-mono text-xs">{{ $path }}</p>
        <p class="mt-1 break-all font-mono text-xs">{{ $contactFormPath }}</p>
        <p class="mt-1 break-all font-mono text-xs">{{ $recaptchaPath }}</p>
        @if ($shared)
            <p class="mt-2 text-muted">The public site reads these files from the same private directory. Maintenance uses 1 to hide the site. The contact form stays available unless its file is 0. reCAPTCHA stays off unless its file is 1. Saving also writes the reCAPTCHA keys beside these files when both environment values are set.</p>
        @else
            <p class="mt-2 text-muted">This copy writes the flags inside its own storage. ahmad.com.bd does not read those files. Set <span class="font-mono">PUBLIC_SITE_MAINTENANCE_FILE</span> to <span class="font-mono">/home/ahmadcom/ahmad-private/public-site-maintenance</span> on the host before the live site will follow these switches.</p>
        @endif
    </div>
</x-layouts.app>
