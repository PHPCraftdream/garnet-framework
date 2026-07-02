```PHP
    class MainController extends FrameworkController {
        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $twig = TwigInit::get(IniConfig::ENV_TWIG_SYSTEM);

            $render = $twig->render('Layout/Main.twig', [
                'styles_assets' => [
                    ApplicationAssetsGen::bootstrapCss(),
                    ApplicationAssetsGen::bootstrapIconsCss(),
                ],
                'js_assets' => [
                    ApplicationAssetsGen::bootstrapJs()
                ],
                'side_menu_items' => [
                    ['label' => 'Dashboard', 'href' => '/', 'active' => true, 'icon' => 'columns'],
                    ['label' => 'Users', 'href' => '/users/', 'icon' => 'people'],
                ],
                'top_menu_items' => [
                    ['label' => 'Dashboard', 'href' => '/', 'active' => true, 'icon' => 'columns'],
                    ['label' => 'Users', 'href' => '/users/', 'icon' => 'people'],
                ],
                'content' => '<h1>Hello, World!</h1>',
            ]);

            return ControllerTools::ok($render);
        }
    }
```
