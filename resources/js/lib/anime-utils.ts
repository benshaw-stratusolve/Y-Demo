import { animate, stagger, createSpring } from 'animejs';

/**
 * Slide a new post in from above with spring physics.
 */
export function animatePostIn(el: HTMLElement): void {
    animate(el, {
        translateY: [-24, 0],
        opacity: [0, 1],
        duration: 500,
        easing: createSpring({ stiffness: 80, damping: 12 }),
    });
}

/**
 * Stagger-animate a batch of posts in on initial page load.
 */
export function animatePostsStagger(els: HTMLElement[]): void {
    animate(els, {
        translateY: [-16, 0],
        opacity: [0, 1],
        duration: 500,
        delay: stagger(60),
        easing: 'easeOutCubic',
    });
}

/**
 * Collapse a post's height to zero, then call onComplete.
 */
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
        easing: 'easeInCubic',
        onComplete,
    });
}

/**
 * Spring-scale the like/heart button on click.
 */
export function animateLikePulse(el: HTMLElement): void {
    animate(el, {
        scale: [1, 1.45, 1],
        duration: 450,
        easing: createSpring({ stiffness: 80, damping: 10 }),
    });
}

/**
 * Spring-bounce a badge element when its count increments.
 */
export function animateBadgeBounce(el: HTMLElement): void {
    animate(el, {
        scale: [1, 1.6, 1],
        duration: 500,
        easing: createSpring({ stiffness: 80, damping: 10 }),
    });
}

/**
 * Slide the feed content in when switching tabs.
 */
export function animateTabTransition(el: HTMLElement, direction: 'left' | 'right'): void {
    const fromX = direction === 'left' ? 40 : -40;
    animate(el, {
        translateX: [fromX, 0],
        opacity: [0, 1],
        duration: 320,
        easing: 'easeOutCubic',
    });
}

/**
 * Slide a chat message bubble in from left (received) or right (sent).
 */
export function animateMessageBubble(el: HTMLElement, isMine: boolean): void {
    animate(el, {
        translateX: [isMine ? 20 : -20, 0],
        opacity: [0, 1],
        duration: 350,
        easing: createSpring({ stiffness: 90, damping: 14 }),
    });
}

/**
 * Start a looping stagger bounce on three typing-indicator dots.
 * Returns a cancel function to stop the loop.
 */
export function startTypingDots(container: HTMLElement): () => void {
    const dots = Array.from(container.querySelectorAll<HTMLElement>('.typing-dot'));
    const anim = animate(dots, {
        translateY: [0, -7, 0],
        duration: 600,
        loop: true,
        delay: stagger(120),
        easing: 'easeInOutSine',
    });
    return () => anim.pause();
}
