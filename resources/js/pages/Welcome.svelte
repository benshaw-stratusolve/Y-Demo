<script lang="ts">
    import { fade } from 'svelte/transition';
    import { onMount } from 'svelte';
    import Backlight from '../components/backlight/Backlight.svelte';
    import FlickeringGrid from '@/components/FlickeringGrid/Flicker.svelte';
    import TypewriterEffect from '@/components/typewriter-effect/TypewriterEffect.svelte';
    import CardContainer from '@/components/3d-card/CardContainer.svelte';
    import CardBody from '@/components/3d-card/CardBody.svelte';
    import CardItem from '@/components/3d-card/CardItem.svelte';

    let ready = $state(false);
    let isMouseEntered = $state(false);

    onMount(() => {
        ready = true;
    });
</script>

<svelte:head>
    <title>What's your Y?</title>
</svelte:head>

{#if ready}
    <div in:fade={{ duration: 600 }} class="relative overflow-hidden min-h-screen bg-black text-white flex flex-col lg:flex-row items-center justify-between p-8 lg:p-24 font-sans">

        
        <FlickeringGrid class="absolute inset-0 z-0" color="rgb(255, 255, 255)" flickerChance={0.3} maxOpacity={0.15} />

        <div class="relative z-10 w-full lg:w-1/2 flex justify-center mb-16 lg:mb-0">
            <CardContainer bind:isMouseEntered>
                <CardBody class="w-auto h-auto bg-transparent">
                    <CardItem {isMouseEntered} translateZ={80} class="w-full">
                        <Backlight blur={0}>
                            <img
                                src="/images/Y-dark-remove.png"
                                alt="Y Logo"
                                class="h-48 sm:h-64 lg:h-[450px] object-contain mix-blend-screen cursor-pointer"
                            />
                        </Backlight>
                    </CardItem>
                </CardBody>
            </CardContainer>
        </div>

        <div class="relative z-10 w-full lg:w-1/2 flex flex-col justify-center max-w-[700px] lg:pl-12">

            <h1 class="text-5xl lg:text-[80px] font-extrabold mb-12 tracking-tight leading-tight">
                <TypewriterEffect
                    words={[{ text: "What's" }, { text: 'your' }, { text: 'Y' }, { text: '?' }]}
                    speed={80}
                />
            </h1>

            <h2 class="text-3xl font-bold mb-8 tracking-tight">
                <TypewriterEffect
                    words={[{ text: 'Join' }, { text: 'today.' }]}
                    speed={100}
                    cursorClass="bg-blue-400"
                />
            </h2>

            <div in:fade={{ duration: 1000, delay: 600 }} class="flex flex-col gap-4 w-full max-w-[300px]">
                <a href="/register" class="relative overflow-hidden bg-blue-500 hover:bg-blue-600 text-white font-bold rounded-full py-2.5 px-4 transition-colors text-center">
                    Create account
                </a>

                <p class="text-[11px] text-neutral-500 mt-2 leading-relaxed">
                    By signing up, you agree to the Terms of Service and Privacy Policy, including Cookie Use.
                </p>

                <div class="mt-1">
                    <h3 class="font-bold text-[19px] mb-5">Already have an account?</h3>
                    <a href="/login" class="relative overflow-hidden bg-transparent border border-neutral-700 hover:bg-blue-500/10 text-blue-500 font-bold rounded-full py-2 px-4 transition-colors text-center">
                        Sign in
                    </a>
                </div>
                </div>
            </div>
        </div>
    
{/if}
