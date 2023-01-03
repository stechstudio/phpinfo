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
    <link rel="shortcut icon" href="https://www.php.net/favicon.ico?v=2">

    <meta name="description" content="View your phpinfo() output in a pretty, responsive interface">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        <?php include(__DIR__ . "/styles.css"); ?>
    </style>
    <script type="module">
        <?php include(__DIR__ . "/app.js"); ?>
    </script>
</head>

<body class="antialiased font-sans">
<div class="h-screen overflow-hidden flex flex-col bg-gray-100" x-data='Navigation'>
    <header class="flex items-center justify-between shadow py-4 px-6 bg-white z-10">
        <div>
            <h1 class="text-xl md:text-2xl font-semibold">PHP v<?php echo $info->version() ?></h1>
            <div class="text-sm text-gray-500"><?php echo $info->config('hostname') ?></div>
        </div>
        <div class="flex items-center gap-2 text-gray-400">
            <img alt="PHP logo" class="hidden md:inline h-12"
                 src="data:image/png;base64,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"/>
            <button @click="showMobileNav()" class="md:hidden p-2 -mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto">
        <div class="flex-1 flex max-w-[96rem] mx-auto">
            <aside class="fixed top-20 bottom-0 overflow-y-auto hidden md:block flex-shrink-0 w-48 lg:w-56 xl:w-64 pl-0.5 py-8 pr-4 xl:pr-8 space-y-px scroll-py-8">
                <?php foreach ($info->modules() as $index => $module) { ?>
                    <a id="nav_<?php echo $module->key() ?>" @click=jump(<?php echo $index ?>) href="#<?php echo $module->key() ?>" class="px-4 py-1 rounded block"
                       :class="selected == '<?php echo $module->key() ?>' ? 'bg-gray-200' : 'hover:bg-white'"
                       @click="select('<?php echo $module->key() ?>')"><?php echo $module->name() ?></a>
                <?php } ?>
            </aside>

            <article class="flex-1 space-y-8 md:ml-52 lg:ml-60 xl:ml-72 py-8 px-4">
                <?php foreach ($info->modules() as $index => $module) { ?>
                    <section x-intersect:enter.margin.-100px="enter(<?php echo $index ?>)"
                             x-intersect:leave.margin.-100px="leave(<?php echo $index ?>)"
                             class="space-y-4 lg:space-y-8  scroll-mt-8" id="<?php echo $module->key() ?>">
                        <h2 class="text-xl font-medium">
                            <a href="#<?php echo $module->key() ?>" @click="jump(<?php echo $index ?>)" class="group inline-flex items-center gap-2">
                                <?php echo $module->name() ?>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-4 h-4 opacity-50">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                </svg>
                            </a>
                        </h2>

                        <?php foreach ($module->groups() as $group) { ?>
                            <div class="shadow rounded-md bg-white overflow-hidden">
                                <table class="w-full text-sm">
                                    <?php if ($group->hasHeadings()) { ?>
                                        <tr class="hidden lg:table-row bg-gray-200 text-gray-900 font-semibold">
                                            <td class="flex-shrink-0 py-2 px-4"><?php echo $group->headings()->get(0) ?></td>
                                            <td class="py-2 px-4"><?php echo $group->headings()->get(1) ?></td>
                                            <?php if ($group->headings()->count() === 3) { ?>
                                                <td class="py-2 px-4"><?php echo $group->headings()->get(2) ?></td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                    <tbody class="divide-y divide-gray-200 ">
                                    <?php foreach ($group->configs() as $index => $config) { ?>
                                        <tr class="flex flex-col py-2 lg:py-0 lg:table-row"
                                            :class="hash == '<?php echo $module->combinedKeyFor($config) ?>' && 'bg-yellow-100'">
                                            <td class="lg:w-1/4 flex-shrink-0 align-top py-2 lg:py-4 pl-4 font-semibold text-gray-500">
                                                <a id="<?php echo $module->combinedKeyFor($config) ?>" href="#<?php echo $module->combinedKeyFor($config) ?>"
                                                   class="inline-flex items-center gap-2 group hover:text-black inline-block active:ring-1 active:ring-indigo-500 scroll-mt-8">
                                                    <?php echo $config->name() ?>

                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden group-hover:inline w-3 h-3  opacity-50">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                                    </svg>
                                                </a>
                                            </td>
                                            <td class="py-2 lg:py-4 px-4 <?php echo $config->localValue() === null ? 'text-gray-400 italic' : 'text-gray-900' ?>"
                                                style="overflow-wrap: anywhere">
                                                <?php if ($group->hasHeadings()) { ?>
                                                    <span class="empty:hidden inline-block w-14 text-center lg:hidden py-1 mr-1 text-xs bg-green-100 text-green-700 font-semibold rounded"><?php echo $group->heading(1) ?></span>
                                                <?php } ?>
                                                <?php echo $config->localValue() ?? 'no value' ?>
                                            </td>
                                            <?php if ($config->hasMasterValue()) { ?>
                                                <td class="py-2 lg:py-4 px-4 {{ $config->masterValue() === null ? 'text-gray-400 italic' : 'text-gray-900' }}"
                                                    style="overflow-wrap: anywhere">
                                                    <?php if ($group->hasHeadings()) { ?>
                                                        <span class="empty:hidden inline-block w-14 text-center lg:hidden py-1 mr-1 text-xs bg-blue-100 text-blue-700 font-semibold rounded"><?php echo $group->heading(2) ?></span>
                                                    <?php } ?>
                                                    <?php echo $config->masterValue() ?? 'no value' ?>
                                                </td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </section>
                <?php } ?>
            </article>
        </div>
    </div>

    <div x-cloak x-transition.opacity x-show="mobileNav" class="fixed inset-0 overflow-hidden bg-gray-900/50 backdrop-blur-sm z-20">
        <div x-show="mobileNav" @click.away="hideMobileNav()" class="fixed top-0 bottom-0 right-0 w-80 bg-gray-800 z-30">
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
            modules: <?php echo json_encode($info->modules()->map->key()->values()) ?>,
            selected: null,
            selectedIndex: null,
            initialized: false,
            init() {
                this.selectModule(this.firstModuleVisible());

                if(window.location.hash != '') {
                    this.hash = window.location.hash.replace("#","");
                }

                setTimeout(() => {
                    document.querySelector(`#nav_${this.selected}`).scrollIntoView({block: "center"});
                    this.initialized = true;
                }, 100);

                window.addEventListener('hashchange', () => {
                    this.hash = window.location.hash.replace("#","");
                }, false);
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
                    this.select(++index);
                }
            },
            jump(index) {
                this.select(index);
            },
            select(index) {
                this.selectedIndex = index;
                this.selected = this.modules[index];
                this.scrollIntoView();
            },
            selectModule(key) {
                this.select(this.modules.indexOf(key));
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
            }
        }))
    });
</script>
</body>
</html>