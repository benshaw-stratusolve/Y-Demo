# Inertia Form Component

> Inertia v3's `<Form>` component for Svelte — how Y handles form submission with validation errors and loading states.

---

## Concept Explained

Inertia v3 for Svelte exposes a `<Form>` component that wraps a native `<form>`, intercepts submission, sends it as an Inertia XHR, and provides `errors` and `processing` state via a snippet. This replaces the older `useForm` hook pattern from v2 — you no longer need to manage a form object; you bind HTML inputs by `name` and let the component handle the rest.

---

## How it's Used in Y

File: `resources/js/pages/settings/Profile.svelte`

### Using the `<Form>` component with Wayfinder

```svelte
<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
</script>

<Form
    {...ProfileController.update.form()}   <!-- Wayfinder provides URL + method -->
    class="space-y-6"
    options={{ preserveScroll: true }}
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" name="name" value={user.name} />
            <InputError message={errors.name} />   <!-- shows validation error -->
        </div>

        <Button type="submit" disabled={processing}>
            {processing ? 'Saving...' : 'Save'}
        </Button>
    {/snippet}
</Form>
```

`ProfileController.update.form()` is a Wayfinder helper that returns `{ action: '/settings/profile', method: 'patch' }` — typed, no magic strings.

### How errors surface

When the server returns validation errors (Laravel's `422` response), Inertia injects them into the `errors` object inside the snippet. Each key matches the field name: `errors.name`, `errors.email`, `errors.bio`. `<InputError message={errors.name} />` renders nothing when `errors.name` is undefined.

### The `processing` flag

`processing` is `true` from when the form is submitted until the response arrives. Disabling the submit button prevents double-submission.

### For complex forms (file uploads, FormData)

When a form needs file uploads (post creation, avatar upload), the `<Form>` component isn't used — instead `router.post()` with a `FormData` object is used directly:

```ts
// resources/js/pages/Dashboard.svelte
const data = new FormData();
data.append('body', postBody);
data.append('image', postImage);
router.post(storePost().url, data, { preserveScroll: true });
```

---

## Key Code Snippet

```svelte
<Form {...ProfileController.update.form()} options={{ preserveScroll: true }}>
    {#snippet children({ errors, processing })}
        <Input name="email" value={user.email} />
        <InputError message={errors.email} />
        <Button disabled={processing}>Save</Button>
    {/snippet}
</Form>
```

---

## Why This Approach

The snippet pattern (`{#snippet children({ errors, processing })}`) is Svelte 5's mechanism for scoped slot content. It gives the `<Form>` component a way to pass state (errors, processing) back down to the content it wraps — without prop drilling or global stores. Compared to Svelte 4's `useForm` hook, this removes the need to manually bind every field to a form object.

---

## Related Notes

- [[Svelte 5 Runes]]
- [[Wayfinder (Type-Safe Routes)]]
- [[Profile + Settings]]
