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

    let {
        mustVerifyEmail,
        status = '',
    }: {
        mustVerifyEmail: boolean;
        status?: string;
    } = $props();

    const user = $derived(page.props.auth.user as any);
</script>

<AppHead title="Profile settings" />

<h1 class="sr-only">Profile settings</h1>

<div class="flex flex-col space-y-6">
    <AvatarUpload avatarUrl={user.avatar_url} userName={user.name} />

    <Heading
        variant="small"
        title="Profile information"
        description="Update your name and email address"
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
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    class="mt-1 block w-full"
                    value={user.email}
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" message={errors.email} />
            </div>

            {#if mustVerifyEmail && !user.email_verified_at}
                <div>
                    <p class="-mt-4 text-sm text-muted-foreground">
                        Your email address is unverified.
                        <TextLink href={send()} as="button">
                            Click here to resend the verification email.
                        </TextLink>
                    </p>

                    {#if status === 'verification-link-sent'}
                        <div class="mt-2 text-sm font-medium text-green-600">
                            A new verification link has been sent to your email address.
                        </div>
                    {/if}
                </div>
            {/if}

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
