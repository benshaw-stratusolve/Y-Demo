<script lang="ts">
	import { onMount } from "svelte";
	import { Moon, Sun } from "lucide-svelte";
	import { cn } from "@/lib/utils";
	import { updateAppearance } from "@/lib/theme.svelte";

	interface AnimatedThemeTogglerProps {
		class?: string;
		duration?: number;
	}

	let { class: className, duration = 400, ...props }: AnimatedThemeTogglerProps = $props();

	let isDark = $state(false);
	let buttonRef: HTMLButtonElement | null = $state(null);

	onMount(() => {
		const updateState = () => {
			isDark = document.documentElement.classList.contains("dark");
		};

		updateState();

		const observer = new MutationObserver(updateState);
		observer.observe(document.documentElement, {
			attributes: true,
			attributeFilter: ["class"],
		});

		return () => observer.disconnect();
	});

	const toggleTheme = async () => {
		if (!buttonRef) return;

		const newTheme = isDark ? "light" : "dark";

		if (!document.startViewTransition) {
			updateAppearance(newTheme);
			return;
		}

		await document.startViewTransition(() => {
			updateAppearance(newTheme);
		}).ready;

		const { top, left, width, height } = buttonRef.getBoundingClientRect();
		const x = left + width / 2;
		const y = top + height / 2;
		const maxRadius = Math.hypot(
			Math.max(left, window.innerWidth - left),
			Math.max(top, window.innerHeight - top)
		);

		document.documentElement.animate(
			{
				clipPath: [
					`circle(0px at ${x}px ${y}px)`,
					`circle(${maxRadius}px at ${x}px ${y}px)`,
				],
			},
			{
				duration,
				easing: "ease-in-out",
				pseudoElement: "::view-transition-new(root)",
			}
		);
	};
</script>

<button bind:this={buttonRef} onclick={toggleTheme} class={cn(className)} {...props}>
	<Sun class="size-5 {isDark ? 'block' : 'hidden'}" />
	<Moon class="size-5 {isDark ? 'hidden' : 'block'}" />
	<span class="sr-only">Toggle theme</span>
</button>
