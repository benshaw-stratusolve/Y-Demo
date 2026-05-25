import { animate, stagger, spring } from 'animejs';

export function animatePostIn(el: HTMLElement): void {
    animate(el, {
        translateY: [-24, 0],
        opacity: [0, 1],
        duration: 500,
        ease: spring({ stiffness: 80, damping: 12 }),
    });
}

export function animatePostsStagger(els: HTMLElement[]): void {
    animate(els, {
        translateY: [-16, 0],
        opacity: [0, 1],
        duration: 500,
        delay: stagger(60),
        ease: 'out(3)',
    });
}

export function animatePostOut(el: HTMLElement, onComplete: () => void): void {
    const height = el.offsetHeight;
    animate(el, {
        height: [height, 0],
        opacity: [1, 0],
        paddingTop: { to: 0 },
        paddingBottom: { to: 0 },
        marginTop: { to: 0 },
        marginBottom: { to: 0 },
        duration: 280,
        ease: 'in(3)',
        onComplete,
    });
}

export function animateLikePulse(el: HTMLElement): void {
    animate(el, {
        keyframes: [
            { scale: 1.45, duration: 150, ease: 'out(3)' },
            { scale: 1, duration: 300, ease: spring({ stiffness: 80, damping: 10 }) },
        ],
    });
}

export function animateBadgeBounce(el: HTMLElement): void {
    animate(el, {
        keyframes: [
            { scale: 1.6, duration: 150, ease: 'out(3)' },
            { scale: 1, duration: 350, ease: spring({ stiffness: 80, damping: 10 }) },
        ],
    });
}

export function animateTabTransition(el: HTMLElement, direction: 'left' | 'right'): void {
    const fromX = direction === 'left' ? 40 : -40;
    animate(el, {
        translateX: [fromX, 0],
        opacity: [0, 1],
        duration: 320,
        ease: 'out(3)',
    });
}

export function animateMessageBubble(el: HTMLElement, isMine: boolean): void {
    animate(el, {
        translateX: [isMine ? 20 : -20, 0],
        opacity: [0, 1],
        duration: 350,
        ease: spring({ stiffness: 90, damping: 14 }),
    });
}

export function startTypingDots(container: HTMLElement): () => void {
    const dots = Array.from(container.querySelectorAll<HTMLElement>('.typing-dot'));
    const anim = animate(dots, {
        keyframes: [
            { translateY: -7, duration: 300, ease: 'out(2)' },
            { translateY: 0, duration: 300, ease: 'in(2)' },
        ],
        loop: true,
        delay: stagger(120),
    });
    return () => anim.pause();
}
