<script module lang="ts">
    import { edit } from '@/routes/profile';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import AppHead from '@/components/AppHead.svelte';
    import AvatarUpload from '@/components/AvatarUpload.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { send } from '@/routes/verification';
    import BanModal from '@/components/BanModal.svelte';
    import { charCounterClass, showCharCounter } from '@/lib/char-counter';

    let {
        mustVerifyEmail,
        status = '',
    }: {
        mustVerifyEmail: boolean;
        status?: string;
    } = $props();

    const user = $derived(page.props.auth.user as any);
    const BIO_MAX = 500;
    let bioValue = $state((page.props.auth.user as any)?.bio ?? '');
    const bioCharsLeft = $derived(BIO_MAX - bioValue.length);
</script>

<AppHead title="Profile settings" />
<BanModal />

<h1 class="sr-only">Profile settings</h1>

<div class="flex flex-col space-y-6">
    {#if status}
        <div class="text-sm font-medium text-green-600 dark:text-green-400">{status}</div>
    {/if}

    <AvatarUpload avatarUrl={user.avatar_url} userName={user.name} />

    <Heading
        variant="small"
        title="Profile information"
        description="Update your name, username and email address"
    />

    <Form
        {...ProfileController.update.form()}
        class="space-y-6"
        options={{ preserveScroll: true }}
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    class="mt-1 block w-full"
                    value={user.name}
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 text-sm">@</span>
                    <Input
                        id="username"
                        name="username"
                        class="mt-1 block w-full pl-7"
                        value={user.username}
                        required
                        autocomplete="off"
                        placeholder="username"
                    />
                </div>
                <InputError class="mt-2" message={errors.username} />
            </div>

            <div class="grid gap-2">
                <Label for="bio">Bio</Label>
                <textarea
                    id="bio"
                    name="bio"
                    rows="3"
                    maxlength="500"
                    placeholder="Tell people a little about yourself..."
                    class="mt-1 block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
                    bind:value={bioValue}
                ></textarea>
                <div class="flex items-center justify-between">
                    <p class="text-xs text-muted-foreground">Max 500 characters</p>
                    {#if showCharCounter(bioCharsLeft)}
                        <span class="text-xs font-semibold tabular-nums {charCounterClass(bioCharsLeft)}">{bioCharsLeft}</span>
                    {/if}
                </div>
                <InputError class="mt-2" message={errors.bio} />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    class="mt-1 block w-full cursor-text"
                    value={user.email}
                    required
                    autocomplete="username"
                    placeholder="Email address"
                    title="Changing your email will require re-verification"
                />
                {#if mustVerifyEmail}
                    <p class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                        <span>⚠</span> Verification pending — check your inbox to confirm your current email.
                    </p>
                {/if}
                <InputError class="mt-2" message={errors.email} />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" disabled={processing} data-test="update-profile-button">
                    Save
                </Button>

                <Dialog>
                    <DialogTrigger>
                        <Button type="button" variant="destructive" data-test="delete-user-button">
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            {...ProfileController.destroy.form()}
                            class="space-y-6"
                            options={{ preserveScroll: true }}
                        >
                            {#snippet children({ errors: deleteErrors, processing: deleteProcessing })}
                                <div class="space-y-3">
                                    <DialogTitle>Are you sure you want to delete your account?</DialogTitle>
                                    <DialogDescription>
                                        Please proceed with caution, this cannot be undone. Once your account
                                        is deleted, all of its resources and data will be permanently removed.
                                        Enter your password to confirm.
                                    </DialogDescription>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="delete-password" class="sr-only">Password</Label>
                                    <PasswordInput
                                        id="delete-password"
                                        name="password"
                                        placeholder="Password"
                                    />
                                    <InputError message={deleteErrors.password} />
                                </div>

                                <DialogFooter class="gap-2">
                                    <DialogClose>
                                        <Button variant="secondary">Cancel</Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={deleteProcessing}
                                        data-test="confirm-delete-user-button"
                                    >
                                        Delete account
                                    </Button>
                                </DialogFooter>
                            {/snippet}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        {/snippet}
    </Form>
</div>
