<script lang="ts">
	import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
	import { cn } from '@/lib/utils';

	type AvatarUser = {
		name?: string | null;
		avatar?: string | null;
		avatar_url?: string | null;
	};

	interface Props {
		user?: AvatarUser | null;
		name?: string | null;
		src?: string | null;
		size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
		class?: string;
		fallbackClass?: string;
	}

	let {
		user = null,
		name = null,
		src = null,
		size = 'md',
		class: className = '',
		fallbackClass = '',
	}: Props = $props();

	const displayName = $derived(name ?? user?.name ?? 'User');
	const avatarSrc = $derived(src ?? user?.avatar_url ?? user?.avatar ?? null);

	const avatarColors = [
		'bg-red-500',
		'bg-orange-500',
		'bg-amber-500',
		'bg-green-500',
		'bg-teal-500',
		'bg-cyan-500',
		'bg-blue-500',
		'bg-indigo-500',
		'bg-violet-500',
		'bg-purple-500',
		'bg-pink-500',
		'bg-rose-500',
	];

	const sizeClasses = {
		xs: 'size-8 text-xs',
		sm: 'size-9 text-xs',
		md: 'size-10 text-sm',
		lg: 'size-12 text-base',
		xl: 'size-20 text-2xl',
	};

	function avatarColor(value: string): string {
		const sum = value.split('').reduce((total, character) => total + character.charCodeAt(0), 0);

		return avatarColors[sum % avatarColors.length];
	}

	function initials(value: string): string {
		return value
			.trim()
			.split(' ')
			.filter(Boolean)
			.map((part) => part[0])
			.join('')
			.toUpperCase()
			.slice(0, 2) || '?';
	}
</script>

<Avatar class={cn(sizeClasses[size], className)}>
	{#if avatarSrc}
		<AvatarImage src={avatarSrc} alt={displayName} />
	{/if}
	<AvatarFallback class={cn(avatarColor(displayName), 'text-white', fallbackClass)}>
		{initials(displayName)}
	</AvatarFallback>
</Avatar>
