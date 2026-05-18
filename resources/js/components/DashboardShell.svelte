<div class="min-h-screen bg-white dark:bg-black text-gray-900 dark:text-gray-100 flex justify-center font-sans pointer-events-none select-none">

    <!-- Left sidebar -->
    <div class="w-[275px] flex-col justify-between py-2 px-4 h-screen hidden sm:flex">
        <div class="flex flex-col gap-2 w-full">
            <div class="p-3 w-fit">
                <svg viewBox="0 0 24 24" class="h-8 w-8 fill-current text-gray-900 dark:text-white">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                </svg>
            </div>
            <div class="flex flex-col gap-1 w-full mt-2">
                {#each ['Home', 'Explore', 'Notifications', 'Messages', 'Profile'] as item}
                    <div class="flex items-center gap-5 p-3 rounded-full w-fit">
                        <div class="w-6 h-6 border-2 border-gray-900 dark:border-white rounded-full opacity-60"></div>
                        <div class="text-xl hidden xl:block opacity-80">{item}</div>
                    </div>
                {/each}
            </div>
            <div class="bg-blue-500 text-white font-bold rounded-full py-3.5 px-8 w-[90%] mt-4 hidden xl:block text-center">
                Post
            </div>
        </div>
        <div class="flex items-center gap-3 p-3 rounded-full w-full mb-4">
            <div class="w-10 h-10 bg-neutral-200 dark:bg-neutral-800 rounded-full shrink-0"></div>
            <div class="flex-col items-start hidden xl:flex">
                <span class="font-bold text-sm">User</span>
                <span class="text-neutral-500 text-sm">@username</span>
            </div>
        </div>
    </div>

    <!-- Main feed -->
    <div class="w-full sm:w-[600px] border-x border-neutral-200 dark:border-neutral-800 min-h-screen">
        <div class="border-b border-neutral-200 dark:border-neutral-800">
            <div class="flex px-4 py-3 pb-0">
                <div class="flex-1 pb-3 flex justify-center font-bold text-[15px]">
                    For you
                    <div class="absolute bottom-0 h-1 w-14 bg-blue-500 rounded-full"></div>
                </div>
                <div class="flex-1 pb-3 flex justify-center font-bold text-[15px] text-neutral-500">
                    Following
                </div>
            </div>
        </div>

        <div class="px-4 py-4 border-b border-neutral-200 dark:border-neutral-800 gap-4 hidden sm:flex">
            <div class="w-10 h-10 bg-neutral-200 dark:bg-neutral-800 rounded-full shrink-0"></div>
            <div class="flex-1">
                <div class="text-neutral-400 py-2 text-xl">What is happening?!</div>
                <div class="h-px bg-neutral-200 dark:bg-neutral-800 mt-4"></div>
            </div>
        </div>

        {#each [
            { author: 'Luke', handle: '@lukeshaw', time: '2h', content: 'Just deployed the new landing page. Really loving the pure OLED black aesthetic and mesh gradients!', likes: 142, reposts: 12, replies: 5 },
            { author: 'AI Engineering Daily', handle: '@ai_eng', time: '5h', content: 'The future of frontend iteration is autonomous agents. Speed and architecture are scaling rapidly.', likes: 890, reposts: 145, replies: 34 },
            { author: 'Ben', handle: '@benshaw', time: '8h', content: 'Building something new. Stay tuned.', likes: 320, reposts: 41, replies: 18 },
        ] as post}
            <div class="p-4 border-b border-neutral-200 dark:border-neutral-800 flex gap-3">
                <div class="w-10 h-10 bg-neutral-200 dark:bg-neutral-800 rounded-full shrink-0 mt-1"></div>
                <div class="flex flex-col w-full">
                    <div class="flex items-center gap-1 text-[15px]">
                        <span class="font-bold">{post.author}</span>
                        <span class="text-neutral-500">{post.handle} · {post.time}</span>
                    </div>
                    <p class="mt-0.5 text-[15px] leading-normal">{post.content}</p>
                    <div class="flex gap-6 text-neutral-500 mt-3 text-[13px]">
                        <span>💬 {post.replies}</span>
                        <span>🔁 {post.reposts}</span>
                        <span>❤️ {post.likes}</span>
                    </div>
                </div>
            </div>
        {/each}
    </div>

    <!-- Right sidebar -->
    <div class="w-[350px] pl-8 py-2 hidden lg:block">
        <div class="bg-neutral-100 dark:bg-neutral-900 rounded-full flex items-center px-4 py-2.5 mb-3">
            <span class="text-neutral-500 text-sm">🔍</span>
            <span class="ml-4 text-neutral-500 text-[15px]">Search</span>
        </div>
        <div class="bg-neutral-100 dark:bg-[#16181c] rounded-2xl p-4 mt-3 border border-neutral-200 dark:border-neutral-800">
            <div class="font-extrabold mb-2">Subscribe to Premium</div>
            <div class="text-[15px] mb-3 text-neutral-500 leading-snug">Subscribe to unlock new features and if eligible, receive a share of ads revenue.</div>
            <div class="bg-blue-500 text-white font-bold rounded-full py-2 px-4 inline-block text-[15px]">Subscribe</div>
        </div>
        <div class="bg-neutral-100 dark:bg-[#16181c] rounded-2xl pt-4 mt-4 border border-neutral-200 dark:border-neutral-800">
            <div class="font-extrabold text-xl px-4 mb-3">What's happening</div>
            {#each ['#SvelteKit', '#Laravel', '#TypeScript'] as tag}
                <div class="px-4 py-3 flex justify-between">
                    <div>
                        <div class="text-[13px] text-neutral-500">Technology · Trending</div>
                        <div class="font-bold mt-0.5 text-[15px]">{tag}</div>
                        <div class="text-[13px] text-neutral-500 mt-1">24.5K posts</div>
                    </div>
                </div>
            {/each}
        </div>
    </div>

</div>
