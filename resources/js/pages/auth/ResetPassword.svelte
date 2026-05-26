<script module lang="ts">
    export const layout = {
        title: 'Reset password',
        description: 'Please enter your new password below',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { update } from '@/routes/password';

    let {
        token,
        email,
        passwordRules,
        tokenInvalid = false,
        tokenInvalidReason = null,
    }: {
        token: string;
        email: string;
        passwordRules: string;
        tokenInvalid?: boolean;
        tokenInvalidReason?: 'expired' | 'used' | null;
    } = $props();
</script>

<AppHead title="Reset password" />

{#if tokenInvalid}
    <div class="grid gap-4">
        <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
            <span class="mt-0.5 shrink-0 text-base">🔗</span>
            <div>
                {#if tokenInvalidReason === 'used'}
                    <p class="font-semibold mb-1">This reset link has already been used.</p>
                    <p class="text-red-700 dark:text-red-400">Your password was already reset using this link. If you need to reset it again, request a new link below.</p>
                {:else}
                    <p class="font-semibold mb-1">This reset link has expired.</p>
                    <p class="text-red-700 dark:text-red-400">Password reset links expire after 15 minutes. Request a new one below.</p>
                {/if}
            </div>
        </div>
        <a
            href="/forgot-password"
            class="inline-flex w-full items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition-colors"
        >
            Request a new reset link
        </a>
    </div>
{:else}
    <div class="mb-5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
        <span class="mt-0.5 shrink-0">⚠</span>
        <span>This link expires <strong>15 minutes</strong> after it was sent and can only be used once. If it has expired, request a new one from the login page.</span>
    </div>

    <Form
        {...update.form()}
        transform={(data) => ({ ...data, token, email })}
        resetOnSuccess={['password', 'password_confirmation']}
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        value={email}
                        class="mt-1 block w-full"
                        readonly
                    />
                    <InputError message={errors.email} class="mt-2" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        placeholder="Password"
                        passwordrules={passwordRules}
                        showHint={true}
                    />
                    <InputError message={errors.password} />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        placeholder="Confirm password"
                        passwordrules={passwordRules}
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    disabled={processing}
                    data-test="reset-password-button"
                >
                    {#if processing}<Spinner />{/if}
                    Reset password
                </Button>
            </div>
        {/snippet}
    </Form>
{/if}
