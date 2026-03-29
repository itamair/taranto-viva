<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* themes/taranto_viva/templates/content/node--geoimage--leaflet-popup.html.twig */
class __TwigTemplate_23b1570fd52a4206850348dfdb7f1837 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 69
        yield "
";
        // line 70
        $context["layout"] = (((($tmp = ($context["layout"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? (("layout--" . \Drupal\Component\Utility\Html::getClass(($context["layout"] ?? null)))) : (""));
        // line 71
        yield "
";
        // line 73
        $context["classes"] = ["node", ("node--type-" . \Drupal\Component\Utility\Html::getClass(CoreExtension::getAttribute($this->env, $this->source,         // line 75
($context["node"] ?? null), "bundle", [], "any", false, false, true, 75))), (((($tmp =         // line 76
($context["layout"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("grid-full") : ("")), (((($tmp =         // line 77
($context["layout"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("ie11-autorow") : ("")), (((($tmp = CoreExtension::getAttribute($this->env, $this->source,         // line 78
($context["node"] ?? null), "isPromoted", [], "method", false, false, true, 78)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--promoted") : ("")), (((($tmp = CoreExtension::getAttribute($this->env, $this->source,         // line 79
($context["node"] ?? null), "isSticky", [], "method", false, false, true, 79)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--sticky") : ("")), (((($tmp =  !CoreExtension::getAttribute($this->env, $this->source,         // line 80
($context["node"] ?? null), "isPublished", [], "method", false, false, true, 80)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--unpublished") : ("")), (((($tmp =         // line 81
($context["view_mode"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? (("node--view-mode-" . \Drupal\Component\Utility\Html::getClass(($context["view_mode"] ?? null)))) : (""))];
        // line 84
        yield "<article";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", [($context["classes"] ?? null)], "method", false, false, true, 84), "html", null, true);
        yield ">
  <div";
        // line 85
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content_attributes"] ?? null), "addClass", ["node__content", ($context["layout"] ?? null)], "method", false, false, true, 85), "html", null, true);
        yield ">
    ";
        // line 87
        yield "    ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->withoutFilter(($context["content"] ?? null), "comment"), "html", null, true);
        yield "
  </div>
</article>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["node", "view_mode", "attributes", "content_attributes", "content"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/taranto_viva/templates/content/node--geoimage--leaflet-popup.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  70 => 87,  66 => 85,  61 => 84,  59 => 81,  58 => 80,  57 => 79,  56 => 78,  55 => 77,  54 => 76,  53 => 75,  52 => 73,  49 => 71,  47 => 70,  44 => 69,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/taranto_viva/templates/content/node--geoimage--leaflet-popup.html.twig", "/var/www/html/web/themes/taranto_viva/templates/content/node--geoimage--leaflet-popup.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 70];
        static $filters = ["clean_class" => 70, "escape" => 84, "without" => 87];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['set'],
                ['clean_class', 'escape', 'without'],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
