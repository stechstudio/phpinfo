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

    <meta name="description" content="View your phpinfo() output in a pretty, responsive interface">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        <?php include(__DIR__ . "/styles.css"); ?>
    </style>
    <script type="module">
        <?php include(__DIR__ . "/app.js"); ?>
    </script>
</head>

<body class="antialiased font-sans text-gray-800">
<div class="" x-data='Navigation' @keydown.window.slash.prevent="$refs.search.focus();">
    <header class="fixed top-0 h-16 lg:h-20 w-full flex items-center justify-between shadow py-4 px-6 xl:px-8 bg-white z-10">
        <div class="flex-1 flex-col items-center gap-4">
            <img class="h-6 md:h-10" src="data:image/svg+xml,%0A%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -1 100 50'%3E%3Cpath d='m7.579 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3Cpath d='m41.093 0 7.314 0-2.067 10.123 6.572 0c3.604 0.071 6.289 0.813 8.056 2.226 1.802 1.413 2.332 4.099 1.59 8.056l-3.551 17.649-7.42 0 3.392-16.854c0.353-1.767 0.247-3.021-0.318-3.763-0.565-0.742-1.784-1.113-3.657-1.113l-5.883-0.053-4.346 21.783-7.314 0 7.632-38.054 0 0'/%3E%3Cpath d='m70.412 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3C/svg%3E%0A"/>
            <div class="md:hidden text-sm text-gray-500">v<?php echo $info->version() ?></div>
        </div>
        <div class="flex-1 flex justify-center">
            <input type="search" class="w-48 md:w-72 lg:w-96 bg-gray-100 rounded-full px-4 py-2 text-sm md:text-base focus:outline-0 focus:bg-gray-200"
                   placeholder="Type to search..." x-model.debounce="search" x-ref="search" @keydown.slash="event.preventDefault();"/>
        </div>
        <div class="flex-1 flex justify-end items-center gap-4 text-gray-400">
            <div class="text-right">
                <h1 class="hidden md:inline text-xl md:text-2xl font-light text-gray-500">v<?php echo $info->version() ?></h1>
            </div>
            <button @click="showMobileNav()" class="md:hidden p-2 -mr-2 border border-gray-300 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </header>

    <div class="fixed w-full top-16 lg:top-20 bottom-0 overflow-y-auto bg-gray-100">
        <div class="flex-1 flex max-w-[96rem] mx-auto">
            <aside class="fixed top-16 lg:top-20 bottom-0 overflow-y-auto hidden md:block flex-shrink-0 w-48 lg:w-56 xl:w-64 py-8 px-4 xl:px-8 space-y-px scroll-py-8">
                <template x-for="(module, index) in info.modules" :key="module.key">
                    <a :id="'nav_' + module.key" @click=jump(index) :href="'#' + module.key" class="px-4 py-1 rounded block"
                       :class="selected == module.key ? 'bg-gray-200' : 'hover:bg-white'"
                       @click="select(index)" x-show="shouldShowSection(module)">
                        <span x-text="module.name"></span>
                    </a>
                </template>
            </aside>

            <article class="flex-1 md:ml-52 lg:ml-60 xl:ml-72 py-8">
                <div class="md:px-4 md:pl-0 xl:pr-8">
                    <template x-for="(module, index) in info.modules" :key="module.key">
                        <section x-intersect:enter.margin.-100px="enter(index)"
                                 x-intersect:leave.margin.-100px="leave(index)"
                                 x-show="shouldShowSection(module)"
                                 class="md:space-y-4 lg:space-y-8 md:mb-4 lg:mb-8 md:scroll-mt-8" :id="module.key">
                            <h2 class="block text-xl font-bold text-gray-900 pl-6 md:pl-0 py-2 md:py-0 sticky md:relative top-0 bg-gray-100 border-b border-gray-200 md:border-0 z-20">
                                <a :href="'#' + module.key" @click="jump(index)" class="group inline-flex items-center gap-2">
                                    <span x-text="module.name"></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-4 h-4 opacity-50">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                    </svg>
                                </a>
                            </h2>

                            <template x-for="(group, index) in module.groups" :key="'group' + index">
                                <div x-show="filteredConfigs(group.configs).length" class="table-wrapper md:shadow md:rounded-md bg-white overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr x-show="group && group.hasHeadings" class="hidden lg:table-row bg-gray-200 text-gray-900 font-semibold">
                                                <td class="flex-shrink-0 py-2 px-4"><span x-text="group.headings[0]"></span></td>
                                                <td class="py-2 px-4"><span x-text="group.headings[1]"></span></td>
                                                <td x-show="group.headings.length == 3" class="py-2 px-4"><span x-text="group.headings[2]"></span></td>
                                            </tr>
                                        </thead>

                                        <tbody class="">
                                            <template x-for="(config, index) in filteredConfigs(group.configs)" :key="config.key">
                                                <tr class="flex flex-col py-2 lg:py-0 lg:table-row border-b border-gray-200"
                                                    :class="hash == config.key && 'bg-yellow-100'">
                                                    <td class="lg:w-1/4 flex-shrink-0 align-top py-2 lg:py-4 pl-6 lg:pl-4 font-semibold text-gray-500">
                                                        <a :id="config.key" :href="'#' + config.key"
                                                           class="inline-flex items-center gap-2 group hover:text-black inline-block active:ring-1 active:ring-indigo-500 scroll-mt-14 md:scroll-mt-8">
                                                            <span x-html="highlighted(config.name)"></span>

                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-3 h-3  opacity-50">
                                                              <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                                            </svg>
                                                        </a>
                                                    </td>
                                                    <td class="py-2 lg:py-4 px-6 lg:px-4" style="overflow-wrap: anywhere"
                                                        :class="config.localValue == null ? 'text-gray-400 italic' : 'text-gray-900'">
                                                        <span x-show="group.hasHeadings" class="empty:hidden inline-block w-14 text-center lg:hidden py-1 mr-1 text-xs bg-green-100 text-green-700 font-semibold rounded" x-text="group.headings[1]"></span>
                                                        <span x-html="highlighted(config.localValue)"></span>
                                                    </td>
                                                    <td x-show="config.hasMasterValue" class="py-2 lg:py-4 px-6 lg:px-4" style="overflow-wrap: anywhere"
                                                        :class="config.masterValue == null ? 'text-gray-400 italic' : 'text-gray-900'">
                                                        <span x-show="group.hasHeadings" class="empty:hidden inline-block w-14 text-center lg:hidden py-1 mr-1 text-xs bg-blue-100 text-blue-700 font-semibold rounded" x-text="group.headings[2]"></span>
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

    <div x-cloak x-transition.opacity x-show="mobileNav" class="fixed inset-0 overflow-hidden bg-gray-900/50 backdrop-blur-sm z-20">
        <div x-show="mobileNav" @click.away="hideMobileNav()" class="fixed top-0 bottom-0 right-0 w-80 bg-gray-800 z-30 ">
            <nav class="absolute inset-0 overflow-y-auto p-6 pt-12 space-y-px text-white">
                <?php foreach ($info->modules() as $index => $module) { ?>
                    <a id="mobile_nav_<?php echo $module->key() ?>" href="#<?php echo $module->key() ?>" @click="hideMobileNav()" class="px-4 py-1 rounded block"
                       :class="selected == '<?php echo $module->key() ?>' ? 'bg-gray-600' : ''"
                       @click="select('<?php echo $module->key() ?>')"><?php echo $module->name() ?></a>
                <?php } ?>
            </nav>

            <div class="absolute top-0 left-0 right-0 flex justify-end bg-gradient-to-b from-gray-800 via-gray-800 to-transparent">
                <button @click="hideMobileNav()" class="m-2 p-2 text-gray-400">
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
            modules: <?php echo json_encode($info->modules()->map->key()->values()) ?>,
            selected: null,
            selectedIndex: null,
            initialized: false,
            search: null,
            init() {
                if(window.location.hash != '') {
                    this.hash = window.location.hash.replace("#","");
                }

                window.addEventListener('hashchange', () => {
                    this.hash = window.location.hash.replace("#","");
                }, false);

                document.addEventListener('alpine:initialized', () => {
                    if(this.hash) {
                        document.querySelector(`#${this.hash}`).scrollIntoView();
                        this.selectModule(this.isModule(this.hash) ? this.hash : this.firstModuleVisible());
                    } else {
                        this.selectModule(this.firstModuleVisible());
                    }

                    setTimeout(() => {
                        document.querySelector(`#nav_${this.selected}`).scrollIntoView({block: "center"});
                        this.initialized = true;
                    }, 100);
                });
            },
            filtered() {
                return !this.unfiltered();
            },
            unfiltered() {
                return this.search == null || this.search == '';
            },
            firstModuleVisible() {
                return Array.from(document.querySelectorAll('section')).filter((section) =>
                    section.getBoundingClientRect().bottom > 100
                )[0].id;
            },
            enter(index) {
                if (this.initialized && (this.selectedIndex == null || index < this.selectedIndex || this.selectedNoLongerVisible())) {
                    this.select(index);
                }
            },
            leave(index) {
                if (this.initialized && (this.selectedIndex == null || this.selectedIndex == index || this.selectedNoLongerVisible())) {
                    this.selectNextIndex();
                }
            },
            jump(index) {
                this.select(index);
            },
            isModule(key) {
                return this.modules.indexOf(key) > -1;
            },
            select(index) {
                if(this.modules[index] === undefined) return;

                this.selectedIndex = index;
                this.selected = this.modules[index];
                this.scrollIntoView();
            },
            selectNextIndex() {
                if(this.unfiltered()) return this.select(this.selectedIndex + 1);

                this.selectModule(this.firstModuleVisible());
            },
            selectModule(key) {
                if(this.isModule(key)) this.select(this.modules.indexOf(key));
            },
            selectedNoLongerVisible() {
                return document.querySelector("#" + this.selected).getBoundingClientRect().bottom < 100;
            },
            scrollIntoView() {
                document.querySelector(`#nav_${this.selected}`).scrollIntoView({block: "nearest"});
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
                if(this.unfiltered()) return text;

                if(text == null) return null;

                return text.replace(new RegExp(this.search,"gi"), "<mark>$&</mark>");
            },
            filteredConfigs(configs)
            {
                if(this.unfiltered()) return configs;

                return configs.filter(config =>
                    config.name.toLowerCase().includes(this.search.toLowerCase())
                    || config.localValue.toLowerCase().includes(this.search.toLowerCase())
                    || (config.hasMasterValue && config.masterValue.toLowerCase().includes(this.search.toLowerCase()))
                );
            },
            shouldShowSection(module) {
                if(!this.initialized || this.unfiltered()) return true;

                return module.groups.filter(
                    (group) => this.filteredConfigs(group.configs).length > 0
                ).length > 0;
            }
        }))
    });
</script>
</body>
</html>