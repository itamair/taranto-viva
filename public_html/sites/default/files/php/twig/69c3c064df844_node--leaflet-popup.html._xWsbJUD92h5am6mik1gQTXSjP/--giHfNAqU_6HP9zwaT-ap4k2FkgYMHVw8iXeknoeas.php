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

/* themes/taranto_viva/templates/content/node--leaflet-popup.html.twig */
class __TwigTemplate_f42534859ecc34bf156e328c73563759 extends Template
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
($context["layout"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("grid-full") : ("")), (((($tmp = CoreExtension::getAttribute($this->env, $this->source,         // line 77
($context["node"] ?? null), "isPromoted", [], "method", false, false, true, 77)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--promoted") : ("")), (((($tmp = CoreExtension::getAttribute($this->env, $this->source,         // line 78
($context["node"] ?? null), "isSticky", [], "method", false, false, true, 78)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--sticky") : ("")), (((($tmp =  !CoreExtension::getAttribute($this->env, $this->source,         // line 79
($context["node"] ?? null), "isPublished", [], "method", false, false, true, 79)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--unpublished") : ("")), (((($tmp =         // line 80
($context["view_mode"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? (("node--view-mode-" . \Drupal\Component\Utility\Html::getClass(($context["view_mode"] ?? null)))) : (""))];
        // line 83
        yield "<article";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", [($context["classes"] ?? null)], "method", false, false, true, 83), "html", null, true);
        yield ">
  <header class=\"";
        // line 84
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["layout"] ?? null), "html", null, true);
        yield "\">
    ";
        // line 85
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title_prefix"] ?? null), "html", null, true);
        yield "
      ";
        // line 86
        if ((($context["label"] ?? null) &&  !($context["page"] ?? null))) {
            // line 87
            yield "      <h2";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["title_attributes"] ?? null), "addClass", ["node__title"], "method", false, false, true, 87), "html", null, true);
            yield ">
        <a href=\"";
            // line 88
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["url"] ?? null), "html", null, true);
            yield "\" rel=\"bookmark\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["label"] ?? null), "html", null, true);
            yield "</a>
      </h2>
    ";
        }
        // line 91
        yield "    ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title_suffix"] ?? null), "html", null, true);
        yield "
    ";
        // line 92
        if ((($tmp = ($context["display_submitted"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 93
            yield "      <div class=\"node__meta\">
      ";
            // line 94
            if ((($tmp = ($context["author_picture"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 95
                yield "        <div class=\"node__author-image\">
          ";
                // line 96
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["author_picture"] ?? null), "html", null, true);
                yield "
        </div>
      ";
            }
            // line 99
            yield "        <span";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["author_attributes"] ?? null), "html", null, true);
            yield ">
          ";
            // line 100
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("By"));
            yield " ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["author_name"] ?? null), "html", null, true);
            yield ", ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["date"] ?? null), "html", null, true);
            yield "
        </span>
        ";
            // line 102
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["metadata"] ?? null), "html", null, true);
            yield "
      </div>
    ";
        }
        // line 105
        yield "  </header>
  <div";
        // line 106
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content_attributes"] ?? null), "addClass", ["node__content", ($context["layout"] ?? null)], "method", false, false, true, 106), "html", null, true);
        yield ">
    ";
        // line 108
        yield "    ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->withoutFilter(($context["content"] ?? null), "comment"), "html", null, true);
        yield "
  </div>
  ";
        // line 110
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "comment", [], "any", false, false, true, 110)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 111
            yield "    <div id=\"comments\" class=\"";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["layout"] ?? null), "html", null, true);
            yield "\">
      ";
            // line 112
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "comment", [], "any", false, false, true, 112), "html", null, true);
            yield "
    </div>
  ";
        }
        // line 115
        yield "</article>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["node", "view_mode", "attributes", "title_prefix", "label", "page", "title_attributes", "url", "title_suffix", "display_submitted", "author_picture", "author_attributes", "author_name", "date", "metadata", "content_attributes", "content"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/taranto_viva/templates/content/node--leaflet-popup.html.twig";
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
        return array (  155 => 115,  149 => 112,  144 => 111,  142 => 110,  136 => 108,  132 => 106,  129 => 105,  123 => 102,  114 => 100,  109 => 99,  103 => 96,  100 => 95,  98 => 94,  95 => 93,  93 => 92,  88 => 91,  80 => 88,  75 => 87,  73 => 86,  69 => 85,  65 => 84,  60 => 83,  58 => 80,  57 => 79,  56 => 78,  55 => 77,  54 => 76,  53 => 75,  52 => 73,  49 => 71,  47 => 70,  44 => 69,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/taranto_viva/templates/content/node--leaflet-popup.html.twig", "/var/www/html/web/themes/taranto_viva/templates/content/node--leaflet-popup.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 70, "if" => 86];
        static $filters = ["clean_class" => 70, "escape" => 83, "t" => 100, "without" => 108];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['set', 'if'],
                ['clean_class', 'escape', 't', 'without'],
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
