<?php
// If you are copying this file out to be used elsewhere, uncomment the following lines,
// and ensure the path to your composer autoload file is correct.
//
// require __DIR__ . '/../vendor/autoload.php';
// $info = STS\Phpinfo\Info::capture();
?>
<!doctype html>
<html :class="darkMode && 'dark'" x-data="{ darkMode: localStorage.getItem('phpinfo-dark') === 'true' || (!localStorage.getItem('phpinfo-dark') && window.matchMedia('(prefers-color-scheme: dark)').matches) }" class="dark:[color-scheme:dark]">

<head>
    <meta charset="utf-8">
    <title>Pretty PHP Info</title>
    <link rel="shortcut icon" type="image/svg" href="data:image/svg+xml,%0A%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -1 100 50'%3E%3Cpath d='m7.579 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3Cpath d='m41.093 0 7.314 0-2.067 10.123 6.572 0c3.604 0.071 6.289 0.813 8.056 2.226 1.802 1.413 2.332 4.099 1.59 8.056l-3.551 17.649-7.42 0 3.392-16.854c0.353-1.767 0.247-3.021-0.318-3.763-0.565-0.742-1.784-1.113-3.657-1.113l-5.883-0.053-4.346 21.783-7.314 0 7.632-38.054 0 0'/%3E%3Cpath d='m70.412 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3C/svg%3E%0A">

    <meta name="description" content="View your phpinfo() output in a pretty, responsive, searchable interface">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        <?php include(__DIR__ . "/styles.css"); ?>
    </style>
</head>

<body>

<div class="page-wrapper antialiased font-sans text-slate-700 dark:text-slate-400 fixed inset-0 flex flex-col" x-data='Navigation' @keydown.window.slash.prevent="$refs.search.focus();">
    <header class="h-14 lg:h-16 flex-shrink-0 flex items-center justify-between py-3 px-6 xl:px-8 z-30 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
        <div class="flex-1 md:flex items-center gap-3">
            <img class="h-6 md:h-10 dark:invert" src="data:image/svg+xml,%0A%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -1 100 50'%3E%3Cpath d='m7.579 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3Cpath d='m41.093 0 7.314 0-2.067 10.123 6.572 0c3.604 0.071 6.289 0.813 8.056 2.226 1.802 1.413 2.332 4.099 1.59 8.056l-3.551 17.649-7.42 0 3.392-16.854c0.353-1.767 0.247-3.021-0.318-3.763-0.565-0.742-1.784-1.113-3.657-1.113l-5.883-0.053-4.346 21.783-7.314 0 7.632-38.054 0 0'/%3E%3Cpath d='m70.412 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3C/svg%3E%0A"/>
            <span class="hidden md:inline-block text-sm font-mono text-slate-400 dark:text-slate-500"><?php echo $info->version() ?></span>
            <span class="md:hidden text-xs font-mono text-slate-400"><?php echo $info->version() ?></span>
        </div>
        <div class="flex-1 flex justify-center">
            <div class="relative group">
                <input type="search" class="w-48 md:w-72 lg:w-96 rounded-lg px-3 py-1.5 text-sm border border-slate-200 dark:border-slate-700 focus:outline-0 focus:ring-2 focus:ring-slate-900/10 dark:focus:ring-slate-300/20 dark:focus:text-slate-100 bg-transparent dark:bg-transparent placeholder:text-slate-400"
                       placeholder="Type to search..." x-model.debounce="search" x-ref="search" @keydown.stop="" x-on:focus="searchFocused = true" x-on:blur="searchFocused = false"/>
                <div x-show="!searchFocused && isUnfiltered()" class="absolute top-0 right-0 mt-1.5 mr-3 border border-slate-300 dark:border-slate-600 rounded px-1.5 py-0.5 text-xs text-slate-400 dark:text-slate-500 font-mono">/</div>
            </div>
        </div>
        <div class="flex-1 flex justify-end items-center gap-3">
            <!-- Dark mode toggle -->
            <button @click="darkMode = !darkMode; localStorage.setItem('phpinfo-dark', darkMode)" class="hidden md:flex items-center justify-center p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Toggle dark mode">
                <svg x-show="!darkMode" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                </svg>
                <svg x-show="darkMode" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                </svg>
            </button>
            <button @click="showMobileNav()" class="md:hidden p-1.5 -mr-2 text-slate-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto bg-white dark:bg-slate-950">
        <div x-cloak x-show="emptyState" class="max-w-3xl mx-auto mt-20 flex gap-4 justify-center text-slate-500 text-xl">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>

            <div>No search results found</div>
        </div>

        <div class="flex-1 flex max-w-[96rem] mx-auto">
            <!-- Sidebar with fade indicators -->
            <div class="fixed top-14 lg:top-16 bottom-0 hidden md:block w-48 lg:w-56 xl:w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 z-10">
                <aside class="absolute inset-0 overflow-y-auto py-6 px-3 xl:px-4 space-y-0.5 scroll-py-6">
                    <template x-for="module in info.modules" :key="module.key">
                        <a x-show="module.shouldShow"
                           :id="'nav_' + module.key"
                           :href="'#' + module.key" @click=jump(module.key)
                           class="px-3 py-1 rounded-md block text-[13px]" :class="selected == module.key ? 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-200 font-medium' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50'">
                            <span x-text="module.name"></span>
                        </a>
                    </template>
                </aside>
                <div class="pointer-events-none absolute bottom-0 left-0 right-0 h-12 bg-gradient-to-t from-white dark:from-slate-950 to-transparent"></div>
                <div class="pointer-events-none absolute top-0 left-0 right-0 h-6 bg-gradient-to-b from-white dark:from-slate-950 to-transparent"></div>
            </div>

            <article class="flex-1 md:ml-52 lg:ml-60 xl:ml-72 py-8">
                <div class="md:px-4 md:pl-0 xl:pr-8">
                    <template x-for="module in info.modules" :key="module.key">
                        <section x-intersect:enter.margin.-100px="enter(module.key)"
                                 x-intersect:leave.margin.-100px="leave(module.key)"
                                 x-show="module.shouldShow"
                                 class="md:space-y-3 lg:space-y-5 md:mb-6 lg:mb-10 md:scroll-mt-6" :id="module.key">
                            <h2 class="block text-lg font-semibold tracking-tight pl-6 md:pl-0 py-2 md:py-0 sticky md:relative top-0 border-b border-slate-100 dark:border-slate-800 md:border-0 z-20 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100">
                                <a :href="'#' + module.key" @click="jump(module.key)" class="group inline-flex items-center gap-2">
                                    <span x-text="module.name"></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-4 h-4 opacity-50">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                    </svg>
                                </a>
                            </h2>

                            <template x-for="group in module.groups" :key="group.key">
                                <div x-show="group.shouldShow" :id="group.key" class="md:scroll-mt-8">
                                    <h3 x-show="group.name" class="pl-6 md:pl-0 text-sm font-medium text-slate-500 dark:text-slate-400 my-3">
                                        <a :href="'#' + group.key" class="group inline-flex items-center gap-2">
                                            <span x-text="group.name"></span>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-4 h-4 opacity-50">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                            </svg>
                                        </a>
                                    </h3>
                                    <div x-show="group.configs.length > 0"
                                         class="table-wrapper md:border border-slate-200 dark:border-slate-800 md:rounded-lg overflow-hidden">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr x-show="group && group.headings.length > 0" class="hidden lg:table-row border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                                                    <th class="text-left text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 py-2.5 px-4"><span x-text="group.headings[0]"></span></th>
                                                    <th class="text-left text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 py-2.5 px-4"><span x-text="group.headings[1]"></span></th>
                                                    <th x-show="group.headings.length == 3" class="text-left text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 py-2.5 px-4"><span x-text="group.headings[2]"></span></th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <template x-for="config in group.configs" :key="config.key">
                                                    <tr class="flex flex-col py-1.5 lg:py-0 lg:table-row border-b border-slate-100 dark:border-slate-800/75 last:border-0 lg:hover:bg-slate-50/75 lg:dark:hover:bg-slate-800/40 transition-colors"
                                                        x-show="config.shouldShow"
                                                        :class="hash == config.key && 'bg-blue-50 dark:bg-blue-950/30 lg:hover:bg-blue-50 lg:dark:hover:bg-blue-950/30'">
                                                        <td class="lg:w-1/4 flex-shrink-0 align-top py-1 lg:py-2.5 pl-6 lg:pl-4 font-medium text-slate-500 dark:text-slate-400">
                                                            <a :id="config.key" :href="'#' + config.key"
                                                               class="inline-flex items-center gap-2 group hover:text-slate-900 dark:hover:text-slate-200 scroll-mt-14 md:scroll-mt-8"
                                                               :class="hash == config.key && 'dark:text-slate-200'"
                                                            >
                                                                <span x-html="formatted(config.name)"></span>

                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-3 h-3 opacity-50">
                                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                                                </svg>
                                                            </a>
                                                        </td>
                                                        <td class="py-1 lg:py-2.5 px-6 lg:px-4 group/cell relative text-slate-600 dark:text-slate-300" style="overflow-wrap: anywhere"
                                                            :class="isNoValue(config.localValue) && 'text-slate-300 dark:text-slate-600 italic'">
                                                            <span x-show="group.headings.length > 0" class="empty:hidden inline-block w-14 text-center lg:hidden py-0.5 mr-1 text-xs font-medium rounded border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400" x-text="group.shortHeadings[1]"></span>
                                                            <span x-html="isLongValue(config.localValue) && !config.expanded ? truncateValue(formatted(config.localValue)) : formatted(config.localValue)"></span>
                                                            <button x-show="isLongValue(config.localValue)" @click="config.expanded = !config.expanded" class="ml-1 text-xs text-blue-600 dark:text-blue-400 hover:underline" x-text="config.expanded ? 'show less' : 'show more'"></button>
                                                            <button x-show="!isNoValue(config.localValue)" @click="copyValue(config.localValue)" class="copy-btn hidden lg:group-hover/cell:inline-flex absolute top-2 right-2 items-center justify-center p-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 text-xs" title="Copy value">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                                                </svg>
                                                            </button>
                                                        </td>
                                                        <td x-show="config.hasMasterValue" class="py-1 lg:py-2.5 px-6 lg:px-4 group/cell relative text-slate-600 dark:text-slate-300" style="overflow-wrap: anywhere"
                                                            :class="isNoValue(config.masterValue) && 'text-slate-300 dark:text-slate-600 italic'">
                                                            <span x-show="group.headings.length > 0" class="empty:hidden inline-block w-14 text-center lg:hidden py-0.5 mr-1 text-xs font-medium rounded border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400" x-text="group.shortHeadings[2]"></span>
                                                            <span x-html="formatted(config.masterValue)"></span>
                                                            <button x-show="!isNoValue(config.masterValue)" @click="copyValue(config.masterValue)" class="copy-btn hidden lg:group-hover/cell:inline-flex absolute top-2 right-2 items-center justify-center p-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 text-xs" title="Copy value">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                                                </svg>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div x-show="group.note" x-html='group.note ? group.note.replaceAll("\n","<br>") : ""'
                                         class="my-3 text-xs md:text-sm text-slate-500 dark:text-slate-400 md:border border-slate-200 dark:border-slate-800 p-4 md:rounded-lg"></div>
                                </div>
                            </template>
                        </section>
                    </template>
                </div>
            </article>
        </div>
    </div>

    <div x-cloak x-transition.opacity x-show="mobileNav" class="absolute inset-0 overflow-hidden bg-slate-900/50 backdrop-blur-sm z-40">
        <div x-show="mobileNav" @click.away="hideMobileNav()" class="fixed top-0 bottom-0 right-0 w-80 bg-slate-800 dark:bg-slate-700 z-50 ">
            <nav class="absolute inset-0 overflow-y-auto p-6 pt-16 space-y-px text-white">
                <template x-for="module in info.modules" :key="module.key">
                    <a x-show="module.shouldShow"
                       :id="'mobile_nav_' + module.key"
                       :href="'#' + module.key" @click="hideMobileNav()"
                       class="px-4 py-1 rounded block"
                       :class="selected == module.key ? 'bg-slate-600' : ''"
                       @click="selectModule(module.key)"
                       x-text="module.name"></a>
                </template>
            </nav>

            <div class="absolute top-0 left-0 right-0 flex justify-end bg-gradient-to-b from-slate-800 dark:from-slate-700 via-slate-800/75 dark:via-slate-700/75 to-transparent">
                <button @click="hideMobileNav()" class="mt-3 mr-4 p-2 bg-slate-800 dark:bg-slate-700 text-slate-400 border border-slate-600 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Copy toast -->
    <div x-cloak x-show="copyToast" x-transition.opacity class="fixed bottom-6 right-6 z-50 bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 text-xs font-medium px-3 py-2 rounded-md">
        Copied to clipboard
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('Navigation', () => ({
            hash: null,
            mobileNav: false,
            info: <?php echo json_encode($info) ?>,
            sections: <?php echo json_encode($info->modules()->map->key()->values()) ?>,
            selected: null,
            selectedIndex: null,
            initialized: false,
            search: null,
            searchFocused: false,
            emptyState: false,
            copyToast: false,
            init() {
                if(window.location.hash != '') this.hash = window.location.hash.replace("#","");
                window.addEventListener('hashchange', () => this.hash = window.location.hash.replace("#",""), false);

                document.addEventListener('alpine:initialized', () => {
                    if(this.hash) {
                        document.querySelector(`#${this.hash}`) && document.querySelector(`#${this.hash}`).scrollIntoView();
                        this.selectModule(this.isModule(this.hash) ? this.hash : this.firstSectionVisible());
                    } else {
                        this.selectModule(this.firstSectionVisible());
                    }

                    document.querySelector(`#nav_${this.selected}`).scrollIntoView({block: "center"});
                    this.initialized = true;
                });

                this.allVisible();

                this.$watch('search', () => this.isFiltered() ? this.applyVisibleFlags() : this.allVisible());
            },
            allVisible() {
                this.info.modules.forEach((module) => {
                    module.groups.forEach((group) => {
                        group.configs.forEach((config) => config.shouldShow = true);
                        group.shouldShow = true;
                    });

                    module.shouldShow = true;
                });
                this.emptyState = false;
            },
            applyVisibleFlags() {
                let term = this.search.toLowerCase();

                this.info.modules.forEach((module) => {
                    let moduleNameMatch = module.name.toLowerCase().includes(term);

                    module.groups.forEach((group) => {
                        group.configs.forEach((config) => {
                            config.shouldShow = moduleNameMatch
                                || config.name.toLowerCase().includes(term)
                                || (config.localValue && config.localValue.toLowerCase().includes(term))
                                || (config.hasMasterValue && config.masterValue && config.masterValue.toLowerCase().includes(term));
                        });

                        group.shouldShow = moduleNameMatch
                            ? group.configs.length > 0
                            : group.configs.filter((config) => config.shouldShow).length > 0;
                    });

                    module.shouldShow = moduleNameMatch || module.groups.filter((group) => group.shouldShow).length > 0;
                });

                this.emptyState = this.info.modules.filter((module) => module.shouldShow).length === 0;
            },
            isFiltered() {
                return !this.isUnfiltered();
            },
            isUnfiltered() {
                return this.search == null || this.search == '';
            },
            isNoValue(val) {
                return val == null || val === 'no value';
            },
            isLongValue(val) {
                return val && val.length > 200;
            },
            truncateValue(html) {
                if(!html) return html;
                let text = html.replace(/<[^>]+>/g, '');
                if(text.length <= 200) return html;
                return html.substring(0, 200) + '...';
            },
            copyValue(val) {
                if(!val || val === 'no value') return;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(val);
                } else {
                    let ta = document.createElement('textarea');
                    ta.value = val;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                this.copyToast = true;
                setTimeout(() => this.copyToast = false, 1500);
            },
            firstSectionVisible() {
                let first = Array.from(document.querySelectorAll('section')).filter((section) =>
                    section && section.getBoundingClientRect().bottom > 100
                )[0];

                return first ? first.id : null;
            },
            enter(key) {
                let index = this.sections.indexOf(key);

                if (this.initialized && (this.selectedIndex == null || index < this.selectedIndex || this.selectedNoLongerVisible())) {
                    this.select(index);
                }
            },
            leave(key) {
                let index = this.sections.indexOf(key);

                if (this.initialized && (this.selectedIndex == null || this.selectedIndex == this.sections.indexOf(key) || this.selectedNoLongerVisible())) {
                    this.selectNextIndex();
                }
            },
            jump(key) {
                this.selectModule(key);
            },
            isModule(key) {
                return this.sections.indexOf(key) > -1;
            },
            select(index) {
                if(this.sections[index] === undefined) return;

                this.selectedIndex = index;
                this.selected = this.sections[index];
                this.scrollIntoView();
            },
            selectNextIndex() {
                if(this.isUnfiltered()) return this.select(this.selectedIndex + 1);

                this.selectModule(this.firstSectionVisible());
            },
            selectModule(key) {
                if(this.isModule(key)) this.select(this.sections.indexOf(key));
            },
            selectedNoLongerVisible() {
                let el = document.querySelector("#" + this.selected);

                return el == null || el.getBoundingClientRect().bottom < 100;
            },
            scrollIntoView() {
                let el = document.querySelector(`#nav_${this.selected}`);

                if(el) el.scrollIntoView({block: "nearest"});
            },
            showMobileNav() {
                document.body.style = "overflow-y: hidden";
                this.mobileNav = true;
                this.$nextTick(() => document.querySelector(`#mobile_nav_${this.selected}`).scrollIntoView({block: "center"}));
            },
            hideMobileNav() {
                document.body.style = "";
                this.mobileNav = false;
            },
            escapeRegex(str) {
                return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            },
            formatted(text) {
                if(text) text = text.replaceAll("\n", "<br>");

                if(this.isUnfiltered() || text == null) return text;

                try {
                    return text.replace(new RegExp(this.escapeRegex(this.search),"gi"), "<mark>$&</mark>");
                } catch(e) {
                    return text;
                }
            }
        }))
    });
</script>
<script type="module">
    <?php include(__DIR__ . "/app.js"); ?>
</script>
</body>
</html>
