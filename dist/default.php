<?php
// If you are copying this file out to be used elsewhere, uncomment the following lines,
// and ensure the path to your composer autoload file is correct.
//
// require __DIR__ . '/../vendor/autoload.php';
// $info = STS\Phpinfo\Info::capture();
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Pretty PHP info</title>
    <link rel="shortcut icon" type="image/svg" href="data:image/svg+xml,%0A%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -1 100 50'%3E%3Cpath d='m7.579 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3Cpath d='m41.093 0 7.314 0-2.067 10.123 6.572 0c3.604 0.071 6.289 0.813 8.056 2.226 1.802 1.413 2.332 4.099 1.59 8.056l-3.551 17.649-7.42 0 3.392-16.854c0.353-1.767 0.247-3.021-0.318-3.763-0.565-0.742-1.784-1.113-3.657-1.113l-5.883-0.053-4.346 21.783-7.314 0 7.632-38.054 0 0'/%3E%3Cpath d='m70.412 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3C/svg%3E%0A">

    <meta name="description" content="View your phpinfo() output in a pretty, responsive, searchable interface">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        <?php include(__DIR__ . "/styles.css"); ?>
    </style>
    <script type="module">
        <?php include(__DIR__ . "/app.js"); ?>
    </script>
</head>

<body class="antialiased font-sans text-slate-700 dark:text-slate-400">
<div class="" x-data='Navigation' @keydown.window.slash.prevent="$refs.search.focus();">
    <header class="fixed top-0 h-16 lg:h-20 w-full flex items-center justify-between shadow py-4 px-6 xl:px-8 z-10 bg-white dark:bg-slate-800 dark:border-b border-slate-700">
        <div class="flex-1 md:flex items-center gap-2">
            <img class="h-6 md:h-10 dark:invert" src="data:image/svg+xml,%0A%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -1 100 50'%3E%3Cpath d='m7.579 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3Cpath d='m41.093 0 7.314 0-2.067 10.123 6.572 0c3.604 0.071 6.289 0.813 8.056 2.226 1.802 1.413 2.332 4.099 1.59 8.056l-3.551 17.649-7.42 0 3.392-16.854c0.353-1.767 0.247-3.021-0.318-3.763-0.565-0.742-1.784-1.113-3.657-1.113l-5.883-0.053-4.346 21.783-7.314 0 7.632-38.054 0 0'/%3E%3Cpath d='m70.412 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3C/svg%3E%0A"/>
            <div class="text-sm md:text-xl font-light text-slate-500">v<?php echo $info->version() ?></div>
        </div>
        <div class="flex-1 flex justify-center">
            <div class="relative group">
                <input type="search" class="w-48 md:w-72 lg:w-96 rounded-full px-4 py-2 text-sm md:text-base border border-slate-100 dark:border-slate-700 focus:outline-0 focus:bg-white focus:border-slate-400 dark:focus:bg-slate-600 dark:focus:text-slate-100 bg-slate-100 dark:bg-slate-700"
                       placeholder="Type to search..." x-model.debounce="search" x-ref="search" @keydown.stop="" x-on:focus="searchFocused = true" x-on:blur="searchFocused = false"/>
                <div x-show="!searchFocused && isUnfiltered()" class="absolute top-0 right-0 mt-2 mr-4 bg-slate-200 dark:bg-slate-600 rounded px-2 py-1 text-xs text-slate-500 dark:text-slate-400">/</div>
            </div>
        </div>
        <div class="flex-1 flex justify-end items-center gap-4 text-slate-400">

            <button @click="showMobileNav()" class="md:hidden p-2 -mr-2 border border-slate-300 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </header>

    <div class="fixed w-full top-16 lg:top-20 bottom-0 overflow-y-auto bg-slate-100 dark:bg-slate-900">
        <div x-cloak x-show="emptyState" class="max-w-3xl mx-auto mt-20 flex gap-4 justify-center text-slate-500 text-xl">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>

            <div>No search results found</div>
        </div>

        <div class="flex-1 flex max-w-[96rem] mx-auto">
            <aside class="fixed top-16 lg:top-20 bottom-0 overflow-y-auto hidden md:block flex-shrink-0 w-48 lg:w-56 xl:w-64 py-8 px-4 xl:px-8 space-y-px scroll-py-8">
                <template x-for="module in info.modules" :key="module.key">
                    <a x-show="module.shouldShow"
                       :id="'nav_' + module.key"
                       :href="'#' + module.key" @click=jump(module.key)
                       class="px-4 py-1 rounded block" :class="selected == module.key ? 'bg-slate-200 dark:bg-slate-800' : 'hover:bg-white dark:hover:bg-black/25'">
                        <span x-text="module.name"></span>
                    </a>
                </template>
            </aside>

            <article class="flex-1 md:ml-52 lg:ml-60 xl:ml-72 py-8">
                <div class="md:px-4 md:pl-0 xl:pr-8">
                    <template x-for="module in info.modules" :key="module.key">
                        <section x-intersect:enter.margin.-100px="enter(module.key)"
                                 x-intersect:leave.margin.-100px="leave(module.key)"
                                 x-show="module.shouldShow"
                                 class="md:space-y-4 lg:space-y-8 md:mb-4 lg:mb-8 md:scroll-mt-8" :id="module.key">
                            <h2 class="block text-xl font-bold pl-6 md:pl-0 py-2 md:py-0 sticky md:relative top-0 border-b border-slate-200 dark:border-slate-700 md:border-0 z-20 bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-slate-200">
                                <a :href="'#' + module.key" @click="jump(module.key)" class="group inline-flex items-center gap-2">
                                    <span x-text="module.name"></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-4 h-4 opacity-50">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                    </svg>
                                </a>
                            </h2>

                            <template x-for="(group, index) in module.groups" :key="'group' + index">
                                <div x-show="group.shouldShow" class="table-wrapper md:shadow dark:shadow-none md:rounded-md overflow-hidden bg-white dark:bg-slate-800/60 md:dark:ring-1 dark:ring-slate-700 dark:ring-inset">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr x-show="group && group.hasHeadings" class="hidden lg:table-row bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                                                <th class="text-left font-semibold py-2 px-4"><span x-text="group.headings[0]"></span></th>
                                                <th class="text-left font-semibold py-2 px-4"><span x-text="group.headings[1]"></span></th>
                                                <th x-show="group.headings.length == 3" class="text-left font-semibold py-2 px-4"><span x-text="group.headings[2]"></span></th>
                                            </tr>
                                        </thead>

                                        <tbody class="">
                                            <template x-for="config in group.configs" :key="config.key">
                                                <tr class="flex flex-col py-2 lg:py-0 lg:table-row border-b border-slate-200 dark:border-slate-700/75"
                                                    x-show="config.shouldShow"
                                                    :class="hash == config.key && 'bg-yellow-100'">
                                                    <td class="lg:w-1/4 flex-shrink-0 align-top py-2 lg:py-4 pl-6 lg:pl-4 font-semibold text-slate-500">
                                                        <a :id="config.key" :href="'#' + config.key"
                                                           class="inline-flex items-center gap-2 group hover:text-black inline-block active:ring-1 active:ring-indigo-500 scroll-mt-14 md:scroll-mt-8">
                                                            <span x-html="highlighted(config.name)"></span>

                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-3 h-3  opacity-50">
                                                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                                            </svg>
                                                        </a>
                                                    </td>
                                                    <td class="py-2 lg:py-4 px-6 lg:px-4" style="overflow-wrap: anywhere"
                                                        :class="config.localValue == null && 'text-slate-400 italic'">
                                                        <span x-show="group.hasHeadings" class="empty:hidden inline-block w-14 text-center lg:hidden py-1 mr-1 text-xs font-semibold rounded bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200" x-text="group.shortHeadings[1]"></span>
                                                        <span x-html="highlighted(config.localValue)"></span>
                                                    </td>
                                                    <td x-show="config.hasMasterValue" class="py-2 lg:py-4 px-6 lg:px-4" style="overflow-wrap: anywhere"
                                                        :class="config.masterValue == null && 'text-slate-400 italic'">
                                                        <span x-show="group.hasHeadings" class="empty:hidden inline-block w-14 text-center lg:hidden py-1 mr-1 text-xs font-semibold rounded  bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-200" x-text="group.shortHeadings[2]"></span>
                                                        <span x-html="highlighted(config.masterValue)"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                        </section>
                    </template>
                </div>
            </article>
        </div>
    </div>

    <div x-cloak x-transition.opacity x-show="mobileNav" class="fixed inset-0 overflow-hidden bg-slate-900/50 backdrop-blur-sm z-20">
        <div x-show="mobileNav" @click.away="hideMobileNav()" class="fixed top-0 bottom-0 right-0 w-80 bg-slate-800 z-30 ">
            <nav class="absolute inset-0 overflow-y-auto p-6 pt-16 space-y-px text-white">
                <template x-for="module in info.modules" :key="module.key">
                    <a x-show="module.shouldShow"
                       :id="'mobile_nav_' + module.key"
                       :href="'#' + module.key" @click="hideMobileNav()"
                       class="px-4 py-1 rounded block"
                       :class="selected == module.key ? 'bg-slate-600' : ''"
                       @click="selectModule(module.key')"
                       x-text="module.name"></a>
                </template>
            </nav>

            <div class="absolute top-0 left-0 right-0 flex justify-end bg-gradient-to-b from-slate-800 via-slate-800 to-transparent">
                <button @click="hideMobileNav()" class="mt-3 mr-4 p-2 bg-slate-800 text-slate-400 border border-slate-600 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
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
            init() {
                if(window.location.hash != '') this.hash = window.location.hash.replace("#","");
                window.addEventListener('hashchange', () => this.hash = window.location.hash.replace("#",""), false);

                document.addEventListener('alpine:initialized', () => {
                    if(this.hash) {
                        document.querySelector(`#${this.hash}`).scrollIntoView();
                        this.selectModule(this.isModule(this.hash) ? this.hash : this.firstModuleVisible());
                    } else {
                        this.selectModule(this.firstModuleVisible());
                    }

                    document.querySelector(`#nav_${this.selected}`).scrollIntoView({block: "center"});
                    this.initialized = true;
                });

                this.$watch('search', () => {
                    if(this.isFiltered()) {
                        this.applyVisibleFlags();
                    } else {
                        this.allVisible();
                    }
                });

                this.allVisible();
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
                this.info.modules.forEach((module) => {
                    module.groups.forEach((group) => {
                        group.configs.forEach((config) => {
                            config.shouldShow = config.name.toLowerCase().includes(this.search.toLowerCase())
                                || config.localValue.toLowerCase().includes(this.search.toLowerCase())
                                || (config.hasMasterValue && config.masterValue.toLowerCase().includes(this.search.toLowerCase()));
                        });

                        group.shouldShow = group.configs.filter((config) => config.shouldShow).length > 0;
                    });

                    module.shouldShow = module.groups.filter((group) => group.shouldShow).length > 0;
                });

                this.emptyState = this.info.modules.filter((module) => module.shouldShow).length === 0;
            },
            isFiltered() {
                return !this.isUnfiltered();
            },
            isUnfiltered() {
                return this.search == null || this.search == '';
            },
            firstModuleVisible() {
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

                if (this.initialized && (this.selectedIndex == null || this.selectedIndex == index || this.selectedNoLongerVisible())) {
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

                this.selectModule(this.firstModuleVisible());
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
            highlighted(text) {
                if(this.isUnfiltered()) return text;

                if(text == null) return null;

                return text.replace(new RegExp(this.search,"gi"), "<mark>$&</mark>");
            }
        }))
    });
</script>
</body>
</html>