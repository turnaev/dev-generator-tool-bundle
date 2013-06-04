<?php

namespace {{ namespace }}\Controller;

use LP\CoreBundle\Controller\BaseController;
{% if 'annotation' == format.routing -%}
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
{% endif %}

class {{ controller }}Controller extends BaseController
{
{# create actions #}
{% for action in actions %}
    {% if 'annotation' == format.routing -%}
    /**
     * @Route("{{ action.route }}")
     {% if 'default' == action.template -%}
     * @Template()
     {% else -%}
     * @Template("{{ action.template }}")
     {% endif -%}
     */
    {% endif -%}
    public function {{ action.name }}(
        {%- if action.placeholders|length > 0 -%}
            ${{- action.placeholders|join(', $') -}}
        {%- endif -%})
    {
    }

{% endfor -%}
}
