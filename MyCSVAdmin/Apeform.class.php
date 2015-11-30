<?php

/*
 * LICENSE
 * 1. If you like to use this class for personal purposes, it's free.
 * 2. For comercial purposes, please contact me (http://maettig.com/email).
 *    I'll send a license to you.
 * 3. When you copy the framework you must copy this notice with the source
 *    code. You may alter the source code, but you have to put the original
 *    with your altered version.
 * 4. The license is for all files included in this bundle.
 *
 * KNOWN BUGS/LIMITATIONS/TODO
 * - Element values will jump around when adding/removing elements on runtime.
 *   Same for multi page forms. Apeform class don't support both cases yet.
 * - <select multiple> is not supported yet (because it's highly useless for
 *   ordinary visitors).
 * - <input type=reset> is not supported because it's useless at all.
 * - Setting a <form target=...> didn't make sense in a self-calling form.
 * - Multiple elements can't handle multiple attributes.
 * - Add <fieldset> and <legend>? Did not work in Netscape 4.x.
 * - Add <button>? Did not work in Netscape 4.x.
 * - Add <optgroup>? Did not work in many browsers.
 * - A challenge/response could be used to avoid reloads and handle multi page
 *   forms. Critical, because this would need to start a session.
 */

/**
 * A very abstract web form builder.
 *
 * This class creates self repeating web forms - so called "Affenformulare" (ape
 * forms) - in a very useful, easy way. The whole process including creation of
 * the form, validation and usage of the form values that the user entered is
 * done in a single script.
 *
 * The class hides the access to POST and global variables and simply returns
 * the submitted values. It offers an easy way to handle input errors (checking
 * valid email adresses for example). It supports all usual form elements
 * including radio buttons, {@link select} boxes, {@link file} upload fields and
 * so on. It provides an own, tiny {@link templates templating} system, so you
 * don't have to deal with HTML at all. It creates labels and access keys
 * according to HTML 4 standard and returns XHTML compatible output. In addition
 * you can {@link addAttribute add} JavaScript handlers to any form element.
 *
 * The class is optimized to be used with the minimum amount of source code. See
 * {@link display()} for a tiny example.
 *
 * Don't hesitate to {@link http://bugs.maettig.com/ report bugs or feature
 * requests}.
 *
 * @author Thiemo Mättig (http://maettig.com/)
 * @version 2009-02-07
 * @package TM
 * @requires PHP 4.0.6 (array_map)
 */
class Apeform
{
    /**
     * Two-dimensional array containing all the web form elements.
     *
     * @var array
     * @access private
     */
    var $_rows = array();

    /**
     * Encryption type of the form. Switches to "multipart/form-data" when using
     * a file upload element.
     *
     * @var string
     * @access private
     */
    var $_encType = "";

    /**
     * Collection of attributes and JavaScript handlers to be added to <form>.
     * "onsubmit" is the only handler allowed here.
     *
     * @var array
     * @access private
     */
    var $_attributes = array();

    /**
     * @var bool Overrides the global magic_quotes_gpc configuration.
     * @access private
     */
    var $magicQuotes = null;

    /**
     * This will be true if one of the web form elements was submitted via POST.
     *
     * @var bool
     * @access private
     */
    var $_isSubmitted = false;

    /**
     * This will be true if error() was called at least once.
     *
     * @var bool
     * @access private
     */
    var $_hasErrors = false;

    /**
     * Defaults to "form". If set to something else, this will be used as prefix
     * for every element name and id. This setting can also be changed when
     * calling {@link Apeform()} or using an extended class.
     *
     * @var string Unique identifier for the form and its elements.
     */
    var $id = "form";

    /**
     * Defaults to the value of {@link id} which defaults to "form". This means,
     * the window scrolls to the top of the form after hitting the submit
     * button. For example <code>$form->anchor = "res";</code> produces
     * <code><form action="example.php#res"></code>. Set to "top" or
     * <code>false</code> to scroll to the top of the page.
     *
     * @var string Anchor name to be targeted when the form was submitted.
     */
    var $anchor = null;

    /**
     * Set to true to automatically add accesskey attributes to labels without
     * an <u>u</u>nderlined character. Defaults to false.
     *
     * @var bool Automatically creates accesskeys for all labels.
     */
    var $autoAccesskeys = false;

    /**
     * Sets the maximum number of characters the user is able to type in
     * {@link text()} and {@link password()} elements. Default is 255
     * characters. This setting can also be changed when calling
     * {@link Apeform()} or using an extended class. It's also possible to set
     * <i>maxLength</i> for single {@link text()} and {@link password()}
     * elements.
     *
     * @var int Default maximum length of the input values.
     */
    var $maxLength = 255;

    /**
     * Default width is 40 characters. This can also be changed when calling
     * {@link Apeform()} or using an extended class. It's also possible to set
     * <i>size</i> for single elements when calling {@link text()} and so on.
     *
     * @var int Default display size of the form elements.
     */
    var $size = 40;

    /**
     * It's important to store uploaded files when using a {@link file()}
     * element because PHP will remove any temporary file immediately. Set this
     * to a temporary directory nearby your scripts using a relative path, e.g.
     * "./temporary". Don't forget to enable writing access for this directory
     * (using chmod).
     *
     * Defaults to "/tmp" (default on Unix/Linux systems). If the directory
     * doesn't exists, one of the TMP/TMPDIR environment variables will be used.
     *
     * The tmpDir can also be changed using an extended class.
     *
     * @var string Directory where to store uploaded files temporary.
     */
    var $tmpDir = "/tmp";

    /**
     * The class uses it's own tiny HTML templating system. This associative
     * array contains all templates used to compile the form. It consists of up
     * to five parts:
     *
     * <code>'form'</code> will be used once as a container for the whole form.
     * It may contain a table header and footer for example.
     * <code>'input'</code> will be used for each form element. It may contain a
     * table row for example. <code>'label'</code>, <code>'error'</code> and
     * <code>'help'</code> are optional. They may contain some special
     * formating, line breaks etc. which should left out if help, error message
     * or label is empty.
     *
     * A basic example (default values are a little bit more complex):
     *
     * <pre>$form->templates = array(
     *     'form'   => "<table>{input}</table>",
     *     'header' => "<tr><th colspan=\"2\">{header}</th></tr>",
     *     'input'  => "<tr{class}><td>{label}</td><td>{input}</td></tr>",
     *     'label'  => "{label}:",
     *     'error'  => "<strong>{error}:</strong>",
     *     'help'   => "", //not used in this example
     *     'accesskey' => "<em class=\"key\">{accesskey}</em>");</pre>
     *
     * See {@link addClass()} for what <code>{class}</code> is for, see
     * {@link text()} for an example what <code>'accesskey'</code> is for.
     *
     * @var array The templates used to compile the form.
     */
    var $templates = array(
        'form' =>
            "<p><table border=\"0\" summary=\"\">\n{input}</table></p>",
            // This is valid (X)HTML but causes problems in Netscape 4.x:
            // "<div style=\"margin:1.12em 0\"><table border=\"0\" summary=\"\">\n{input}</table></div>",
        'header' =>
            "<tr{class}>\n<th colspan=\"2\">{header}</th>\n</tr>\n",
        'input' =>
            "<tr{class}>\n<td align=\"right\" valign=\"top\">{label}</td>\n<td valign=\"top\">{input}{help}</td>\n</tr>\n",
        'label' =>
            "{label}:",
        'error' =>
            '<strong class="error">{error}:</strong>',
        'help' =>
            '<br /><small>{help}</small>',
            // This is "semantical" better because <small> is a physical tag and
            // dropped in XHTML 2.0:
            // '<div style="font-size:smaller">{help}</div>',
        'accesskey' =>
            '<span style="text-decoration:underline">{accesskey}</span>'
            // This may be "semantical" better because <em> has a meaning:
            // '<em style="font-style:normal;text-decoration:underline">{accesskey}</em>'
    );

    /**
     * Creates a new web form builder.
     *
     * Class constructor of the web form builder. Returns a new Apeform object.
     * All parameters are optional. They can also be set using an extended
     * class. Default for <i>maxLength</i> is 255. Default for <i>size</i> is
     * 40. Default for <i>id</i> is "form". Setting <i>magicQuotes</i> the user
     * may disable or enable PHP's magic quotes behaviour manualy, independend
     * what's set in the <code>php.ini</code> (see get_magic_quotes_gpc()).
     * Defaults to the configurations default.
     *
     * @param maxLength int
     * @param size int
     * @param id string
     * @param magicQuotes bool
     * @return Apeform
     */
    function Apeform($maxLength = 0, $size = 0, $id = null, $magicQuotes = null)
    {
        // Set default maximum input length and width of the form elements.
        if ($maxLength > 0) $this->maxLength = (int)$maxLength;
        if ($size > 0) $this->size = (int)$size;

        // For backward compatibility cause parameter #3 moved to position #4.
        if (is_bool($id)) { $magicQuotes = $id; unset($id); }
        elseif (isset($id) && strlen($id)) $this->id = $id;

        if (isset($magicQuotes)) $this->magicQuotes = $magicQuotes;
        // Use default magic_quotes_gpc value if it's still not set.
        if (!isset($this->magicQuotes)) $this->magicQuotes = get_magic_quotes_gpc();
    }

    /**
     * Adds a header or subheading to the form.
     *
     * This is the only element that uses the {@link templates template}
     * <code>'header'</code>.
     *
     * @param header string
     * @return string
     */
    function &header($header)
    {
        $this->_rows[count($this->_rows)] = array('type' => "header",
            'header' => $header,
            'name' => false);
        return $header;
    }

    /**
     * Adds a static text to the form.
     *
     * For example, display a {@link text} element first. If the user entered a
     * valid value, replace the text with a staticText element. Example:
     *
     * <pre>$form = new Apeform();
     * $form->text("Text");
     * if (!$form->isValid()) $form->text("Static");
     * else $form->staticText("Static");
     * $form->submit();
     * $form->display();</pre>
     *
     * @param label string
     * @param help string
     * @param defaultValue string
     * @return string
     */
    function &staticText($label = "", $help = "", $defaultValue = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "static",
            'label' => $label,
            'help' => $help,
            'value' => $defaultValue,
            'name' => $name);
        return $this->_fetchPostedValue();
    }

    /**
     * Adds a text input element to the form.
     *
     * Adds a single line input box to the form. All the arguments may be left
     * out in order from right to left.
     *
     * Use HTML tags for an <code>"<u>u</u>nderlined"</code> character in the
     * <i>label</i> to set an access key for this element. Pressing Alt +
     * character will focus this element later (Shift + Esc + character in
     * Opera). Keep this in mind, this works for almost all elements.
     *
     * If <i>help</i> is set to <code>"Help\t Unit"</code> for example, the text
     * "Unit" will be displayed behind the input field. This works for all
     * element types but makes sense only for a few of them.
     *
     * If one or all of <i>defaultValue</i>, <i>maxLength</i> or <i>size</i> is
     * an array instead of a single value, a multiple input field will be
     * created. Use more than one tab characters in <i>help</i> to add some text
     * between the elements. The following example creates a nice
     * [2004]-[12]-[31] date element made of three input fields:
     *
     * <pre>$date = $form->text("Date", "Year-Month-Day\t-\t-",
     *     array(2004, 12, 31), array(4, 2, 2));
     * echo $date[0]; //outputs 2004
     * //other possibilities to create multiple text fields:
     * $b = $form->text("Multi maxLength", "", "", array(9, 10));
     * $c = $form->text("Multi defaultValue", "", array("", ""));</pre>
     *
     * Returns <i>defaultValue</i> if the form is displayed the first time.
     * After this it returns the value(s) the user entered and submitted. Use
     * the <code>$ref = &$form->text();</code> syntax (note the ampersand) to
     * return the value by reference. This way you are able to change the value
     * displayed in the form (e.g. make it upper case).
     *
     * @param label string
     * @param help string
     * @param defaultValue mixed
     * @param maxLength mixed
     * @param size mixed
     * @return mixed
     */
    function &text($label = "", $help = "", $defaultValue = "", $maxLength = 0,
        $size = 0)
    {
        $count = max(count($defaultValue), count($maxLength), count($size));
        if (is_string($help)) $help = explode("\t", $help, 1 + $count);
        if ($count > 1)
        {
            $defaultValue = (array)$defaultValue;
            for ($i = 1; $i < $count; ++$i)
            {
                if (!isset($help[$i])) $help[$i] = "\n";
                if (!isset($defaultValue[$i])) $defaultValue[$i] = "";
            }
        }
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "text",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'value' => $defaultValue,
            'maxLength' => $maxLength,
            'size' => $size,
            'name' => $name);
        $value = &$this->_fetchPostedValue();
        if (is_array($value) && count($value) < 2) return $value[0];
        else return $value;
    }

    /**
     * Adds a password input element to the form.
     *
     * Adds a password input element that works the same way like
     * {@link text()}. The only difference is, if an error occurs the value will
     * be removed (because the user can't see what he typed before).
     *
     * @param label string
     * @param help string
     * @param defaultValue string
     * @param maxLength int
     * @param size int
     * @return string
     */
    function &password($label = "", $help = "", $defaultValue = "",
        $maxLength = 0, $size = 0)
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "password",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'value' => $defaultValue,
            'maxLength' => $maxLength,
            'size' => $size,
            'name' => $name);
        return $this->_fetchPostedValue();
    }

    /**
     * Adds a text area to the form.
     *
     * Adds a multi line input box to the form. Works similar to {@link text()}.
     *
     * To change the height of the area set the number of <i>rows</i>. Default
     * is 3. Default for <i>cols</i> is 40 (see {@link size}). Default for
     * <i>wrap</i> is "virtual". Other possible values are "off" or
     * <code>false</code>.
     *
     * @param label string
     * @param help string
     * @param defaultValue string
     * @param rows int
     * @param cols int
     * @param wrap mixed
     * @return string
     */
    function &textarea($label = "", $help = "", $defaultValue = "", $rows = 0,
        $cols = 0, $wrap = "virtual")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "textarea",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'value' => $defaultValue,
            'rows' => $rows,
            'cols' => $cols,
            'wrap' => empty($wrap) ? "off" : $wrap,
            'name' => $name);
        return $this->_fetchPostedValue();
    }

    /**
     * Adds a hidden input element to the form.
     *
     * Adds a hidden element to the form. Returns the hidden value. If you need
     * the value before hidden() was called, set a <i>name</i>. This is the only
     * place you need to set a name for an element. This way you are able to
     * fetch the hidden value using <code>$_POST</code> or
     * <code>$_REQUEST['elementName']</code>.
     *
     * @param defaultValue string
     * @param name string
     * @return string
     */
    function &hidden($defaultValue = "", $name = "")
    {
        $id = count($this->_rows);
        $name = $name ? $name : ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "hidden",
          'value' => $defaultValue,
          'name' => $name);
        return $this->_fetchPostedValue();
    }

    /**
     * Adds one or more checkbox elements to the form.
     *
     * Adds one or more checkbox elements to the form. If only one option is
     * given or <i>options</i> is empty a single checkbox will be displayed.
     * Returns a string in this case. If two or more options are given (see
     * {@link select()} for some examples) it will return an array. The
     * <i>defaultValue</i> also have to be an array in this case.
     *
     * @param label string
     * @param help string
     * @param options mixed
     * @param defaultValue mixed
     * @return mixed
     */
    function &checkbox($label, $help = "", $options = "", $defaultValue = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        // Use the label as the only checkbox value if no options are given.
        if (!$options) $options = array($label => "");
        $this->_rows[$id] = array('type' => "checkbox",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'options' => $this->_explodeOptions($options),
            'name' => $name);
        // Default value is an array for multiple checkboxes, a string otherwise.
        if (count($this->_rows[$id]['options']) > 1)
        {
            // Don't use the default value if the form was already submitted.
            $this->_rows[$id]['value'] = $this->_isSubmitted ? array() :
                $this->_explodeOptions($defaultValue);
        }
        else
        {
            $this->_rows[$id]['value'] = $this->_isSubmitted ? "" :
                $defaultValue;
        }
        return $this->_fetchPostedValue();
    }

    /**
     * Adds some radio buttons to the form.
     *
     * Adds two or more radio buttons to the form. See {@link select()} for
     * further explanation.
     *
     * @param label string
     * @param help string
     * @param options mixed
     * @param defaultValue string
     * @return string
     */
    function &radio($label, $help, $options, $defaultValue = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "radio",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'options' => $this->_explodeOptions($options),
            'value' => $defaultValue,
            'name' => $name);
        // If a default array value instead of a array key was given, fix this.
        if (!isset($this->_rows[$id]['options'][$this->magicQuotes ?
            addslashes($defaultValue) : $defaultValue]))
        {
            $this->_rows[$id]['value'] =
                array_search($defaultValue, $this->_rows[$id]['options']);
            if ($this->magicQuotes)
            {
                $this->_rows[$id]['value'] = stripslashes($this->_rows[$id]['value']);
            }
        }
        return $this->_fetchPostedValue();
    }

    /**
     * Adds a select element to the form.
     *
     * Adds a box to the form to select a value out of two or more values. This
     * is almost similar to {@link radio()} except for the way it is rendered.
     *
     * The <i>options</i> may be an associative array, for example
     * <code>array("a" => "Option A", "b" => "Option B")</code>. The values of
     * this array will be displayed, the keys will be submitted. For example,
     * the user selects "Option B" so a "b" will be returned by select(). The
     * <i>options</i> can also be a string, for example
     * <code>"Option A|Option B"</code>. This way the displayed and submitted
     * values will be the same, for example "Option B".
     *
     * Set <i>defaultValue</i> to one of the array keys to select an option by
     * default. Leave it empty to select nothing by default.
     *
     * The <i>size</i> isn't the width but the number of rows of the element.
     * Default is one row.
     *
     * Returns <i>defaultValue</i> if the form is displayed the first time.
     * After this it returns the array key of the selected option or an empty
     * string if nothing was selected. Use the
     * <code>$ref = &$form->select();</code> syntax (note the &) to return the
     * value by reference.
     *
     * @param label string
     * @param help string
     * @param options mixed
     * @param defaultValue string
     * @param size int
     * @return string
     */
    function &select($label, $help, $options, $defaultValue = "", $size = 1)
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "select",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'options' => $this->_explodeOptions($options),
            'value' => $defaultValue,
            'size' => $size,
            'name' => $name);
        // If a default array value instead of a array key was given, fix this.
        if (!isset($this->_rows[$id]['options'][$this->magicQuotes ?
            addslashes($defaultValue) : $defaultValue]))
        {
            $this->_rows[$id]['value'] =
                array_search($defaultValue, $this->_rows[$id]['options']);
            if ($this->magicQuotes)
            {
                $this->_rows[$id]['value'] = stripslashes($this->_rows[$id]['value']);
            }
        }
        return $this->_fetchPostedValue();
    }

    /**
     * Adds a file upload element to the form.
     *
     * Adds a single file upload element to the form. Encryption type of the
     * form will be set to "multipart/form-data" automaticaly. Returns all file
     * information in an associative array. For example
     * <code>$file = $form->file();</code> returns $file['name'], $file['type'],
     * $file['size'], $file['tmp_name'] and $file['error']. Additionaly,
     * $file['unixname'] provides a noncritical file name without any spaces and
     * special characters.
     *
     * Unlike regular upload forms the file is not lost when the script ends.
     * The file will be stored in the temporary directory specified by
     * {@link tmpDir} or in the systems default TMP/TMPDIR. Use copy() to move
     * the file anywhere, for example <code>copy($file['tmp_name'], "target/" .
     * $file['name']);</code>. Don't use move_uploaded_file()!
     *
     * If the file upload element got an {@link error()} the temporary file will
     * be removed immediately. The garbage collection will remove all out-dated
     * temporary files as soon as the next file is uploaded.
     *
     * @param label string
     * @param help string
     * @param size int
     * @return array
     */
    function &file($label = "", $help = "", $size = 0)
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "file",
            'label' => $this->_addAcceskey($label),
            'help' => $help,
            'value' => false,
            'size' => $size,
            'name' => $name);
        // File is the only element that requires another encryption type.
        $this->_encType = "multipart/form-data";

        if (isset($GLOBALS['HTTP_POST_FILES'][$name]) && !isset($_FILES[$name]))
            $_FILES[$name] = &$GLOBALS['HTTP_POST_FILES'][$name];
        // Accept uploaded files only if the file size is greater than zero.
        if (isset($_FILES[$name]) && $_FILES[$name]['size'])
        {
            $this->_rows[$id]['value'] = $_FILES[$name];
            $postedName = &$this->_rows[$id]['value']['name'];
            if (get_magic_quotes_gpc()) $postedName = stripslashes($postedName);
            if ($this->magicQuotes) $postedName = addslashes($postedName);
        }
        // Read meta data from the hidden element if present.
        elseif (isset($_POST[$name . "h"]))
        {
            $this->_rows[$id]['value'] =
                unserialize(stripslashes($_POST[$name . "h"]));
        }
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$name . "h"]))
        {
            $this->_rows[$id]['value'] =
                unserialize(stripslashes($GLOBALS['HTTP_POST_VARS'][$name . "h"]));
        }
        if ($this->_rows[$id]['value'] || isset($_FILES[$name]))
            $this->_isSubmitted = true;

        // Handle the uploaded file and meta data if something is in 'value'.
        if ($this->_rows[$id]['value'])
        {
            $this->_rows[$id]['value']['unixname'] =
                $this->_getUnixName($this->_rows[$id]['value']['name']);
            // Fix for a bug in IE which returns "pjpeg" for progressive JPEGs.
            $this->_rows[$id]['value']['type'] =
                preg_replace('{^image\W\wjpe?g$}is', 'image/jpeg',
                $this->_rows[$id]['value']['type']);

            // Store the file to avoid it to be deleted when the script ends.
            if (is_uploaded_file($this->_rows[$id]['value']['tmp_name']))
            {
                // tempnam() needs an absolute path so use realpath() to be sure.
                $tempnam = tempnam($realpath = realpath($this->tmpDir), "tmp");
                if (!$this->_doGarbageCollection($tempnam))
                {
                    user_error("Apeform::file() failed, tmpDir is not set properly",
                        E_USER_WARNING);
                    return $this->_rows[$id]['value'] = false;
                }
                // Preserve extension to be sure the real content type is used.
                $extension = strrchr($this->_rows[$id]['value']['name'], '.');
                rename($tempnam, $tempnam . $extension);
                $tempnam .= $extension;
                if (!move_uploaded_file($this->_rows[$id]['value']['tmp_name'],
                    $tempnam))
                {
                    return $this->_rows[$id]['value'] = false;
                }
                // Make the temporary path relative again if tmpDir exists.
                if (dirname($tempnam) == $realpath)
                {
                    $tempnam = $this->tmpDir . "/" . basename($tempnam);
                }
                $this->_rows[$id]['value']['tmp_name'] = $tempnam;
            }
            // Force an error if the file was lost for whatever reason.
            if (!is_file($this->_rows[$id]['value']['tmp_name']))
                $this->error();
        }
        // Returns false if there was no file submitted.
        return $this->_rows[$id]['value'];
    }

    /**
     * Generates an usefull Unix filename besides the original one.
     *
     * @param name string The filename to be processed.
     * @param maxLength int Maximum filename length, defaults to 64 (Joliet).
     * @return string
     * @access private
     */
    function _getUnixName($name, $maxLength = 64)
    {
        // Replace German umlauts and other special characters.
        $name = str_replace("ß", "ss", $name);
        // Diphton Ä becomes Äe etc.
        $name = preg_replace('/[\x8C\x9CÄÆÖØÜäæöøü]/', '\0e', $name);
        // Replace Windows-1252 (CP1252) and ISO-8859-1 with ASCII characters.
        $name = strtr($name,
            "\x80\x83\x8A\x8C\x8E\x96\x97\x9A\x9C\x9E\x9F¢£¥©ª­®²³µ¹º" .
            "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ",
            "EfSOZ--sozYcLYca-r23u1o" .
            "AAAAAAACEEEEIIIIDNOOOOOxOUUUUyTaaaaaaaceeeeiiiidnoooooouuuuyty");
        // Replace all remaining special characters with an underscore.
        $name = preg_replace('/[^a-z0-9.-]+/i', '_', $name);
        // Remove all useless underscores, e.g. "_a_a_._a_" becomes "a_a.a".
        $name = preg_replace('/_*\b_*/', '', $name);
        // Crop the filename if it's too long.
        while (strlen($name) > $maxLength)
            $name = preg_replace('/.\b/', '', $name, 1);
        return $name;
    }

    /**
     * Remove some old temporary files which exceeded the timeout. Default for
     * timeout is 1440 seconds (24 minutes, see session.gc_maxlifetime).
     *
     * @param filename string
     * @return bool
     * @access private
     */
    function _doGarbageCollection($filename, $timeout = 1440)
    {
        $dir = dirname($filename) . "/";
        if (!($fp = opendir($dir))) return false;
        while (($file = readdir($fp)) !== false)
        {
            if (strpos($file, "tmp") === 0 && filemtime($dir . $file) < time() - $timeout)
            {
                @unlink($dir . $file);
            }
        }
        closedir($fp);
        return true;
    }

    /**
     * Adds one or more submit buttons to the form.
     *
     * Adds some submit buttons to the form. The <i>value</i> may be a simple
     * string to create a single button. It may be an array to create multiple
     * buttons. It's also possible to use a string for multiple buttons, for
     * example <code>"Button A|Button B"</code>. Returns the value of the button
     * the user pressed.
     *
     * @param value mixed
     * @param help string
     * @return string
     */
    function submit($value = "", $help = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => 'submit',
            'value' => empty($value) ? array("") : $this->_explodeOptions($value),
            'help' => $help,
            'name' => $name);
        if (isset($_POST[$name]))
        {
            $postedValue = $_POST[$name];
        }
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$name]))
        {
            $postedValue = $GLOBALS['HTTP_POST_VARS'][$name];
        }
        if (isset($postedValue))
        {
            if (get_magic_quotes_gpc()) $postedValue = stripslashes($postedValue);
            if (empty($value)) $postedValue = true;
            // Deny unknown values.
            elseif (!in_array($postedValue, $this->_rows[$id]['value'])) return false;
            elseif ($this->magicQuotes) $postedValue = addslashes($postedValue);
            $this->_isSubmitted = true;
            // Return the buttons value if it was clicked before.
            return $postedValue;
        }
        // Return nothing otherwise. Needed to handle multiple buttons.
        return false;
    }

    /**
     * Adds one or more image buttons to the form.
     *
     * <i>Warning! This method is EXPERIMENTAL. The behaviour of this method may
     * change in a future release of this class.</i>
     *
     * @param src string
     * @param help string
     * @return string
     */
    function image($src, $help = "")
    {
        $id = count($this->_rows);
        $name = ($this->id == "form" ? "" : $this->id) . "element" . $id;
        $this->_rows[$id] = array('type' => "image",
            'value' => $this->_explodeOptions($src),
            'help' => $help,
            'name' => $name);
        if (isset($_POST[$name . '_x']))
        {
            $x = $_POST[$name . '_x'];
            $y = $_POST[$name . '_y'];
        }
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$name. '_x']))
        {
            $x = $GLOBALS['HTTP_POST_VARS'][$name . '_x'];
            $y = $GLOBALS['HTTP_POST_VARS'][$name . '_y'];
        }
        if (isset($x))
        {
            $this->_isSubmitted = true;
            return array(0 => $x, 1 => $y, 'x' => $x, 'y' => $y);
        }
        return false;
    }

    /**
     * Add an acceskey attribute if missing and if autoAccesskeys is set.
     *
     * @param label string
     * @return string
     * @access private
     */
    function _addAcceskey($label)
    {
        if (!$this->autoAccesskeys || stristr($label, "<u>")) return $label;
        $c = strtolower(preg_replace('/[^a-z]+/isS', '', $label));
        for ($i = 0, $len = strlen($c); $i < $len; ++$i)
        {
            if (empty($this->_accesskeys[$c{$i}]))
            {
                $this->_accesskeys[$c{$i}] = true;
                return preg_replace('/' . $c{$i} . '/i', '<u>\0</u>', $label, 1);
            }
        }
        return $label;
    }

    /**
     * Explodes a string at "\t" or at "|".
     *
     * @param options mixed
     * @return array
     * @access private
     */
    function _explodeOptions($options)
    {
        // Options can be an associative array or a string, e.g. "a|b".
        if (!is_array($options))
        {
            if (strlen($options) < 1) return array();
            if (strpos($options, "\t") === false)
            {
                $options = strtr($options, array("\\\\" => "\\", "\|" => "|", "|" => "\t"));
            }
            $exploded = explode("\t", $options);
            $options = array();
            // Copy all options to an array, e.g. "a" => "a", "b" => "b".
            foreach ($exploded as $value) $options[$value] = $value;
        }
        if ($this->magicQuotes)
        {
            $array = $options;
            $options = array();
            foreach ($array as $key => $value) $options[addslashes($key)] = $value;
        }
        return $options;
    }

    /**
     * @return mixed
     * @access private
     */
    function &_fetchPostedValue()
    {
        // Create a shortcut reference for use in the following lines.
        $element = &$this->_rows[count($this->_rows) - 1];
        if (isset($_POST[$element['name']]))
        {
            $postedValue = $_POST[$element['name']];
        }
        elseif (isset($GLOBALS['HTTP_POST_VARS'][$element['name']]))
        {
            $postedValue = $GLOBALS['HTTP_POST_VARS'][$element['name']];
        }
        if (isset($postedValue))
        {
            if (get_magic_quotes_gpc())
            {
                $postedValue = is_array($postedValue)
                    ? array_map('stripslashes', $postedValue)
                    : stripslashes($postedValue);
            }
            if (strpos(implode("", (array)$postedValue), "\0"))
                $this->error();
            // Strip all null characters, they can't be entered anywhere.
            $postedValue = str_replace("\0", "", $postedValue);
            switch ($element['type'])
            {
                case "text":
                case "password":
                    if (strpos(implode("", (array)$postedValue), "\n"))
                        $this->error();
                    $postedValue = preg_replace('/[\r\n]+/s', ' ', $postedValue);
                    foreach ((array)$postedValue as $i => $v)
                    {
                        if (!is_array($element['maxLength']) && !empty($element['maxLength']))
                            $l = $element['maxLength'];
                        elseif (!empty($element['maxLength'][$i]))
                            $l = $element['maxLength'][$i];
                        else $l = $this->maxLength;
                        if (strlen($v) > $l)
                        {
                            $this->error();
                            if (is_array($postedValue)) $postedValue[$i] = substr($v, 0, $l);
                            else $postedValue = substr($v, 0, $l);
                        }
                    }
                    break;
                case "checkbox":
                case "radio":
                case "select":
                    foreach ((array)$postedValue as $i => $v)
                    {
                        if (!isset($element['options'][$v]))
                        {
                            $this->error();
                            if (is_array($postedValue)) unset($postedValue[$i]);
                            else $postedValue = "";
                        }
                    }
                    break;
            }
            $element['value'] = $postedValue;
            $this->_isSubmitted = true;
        }
        if ($this->magicQuotes)
        {
            $element['value'] = is_array($element['value'])
                ? array_map('addslashes', $element['value'])
                : addslashes($element['value']);
        }
        return $element['value'];
    }

    /**
     * Gets the name of the element added last.
     *
     * <i>Note: Normally, you don't need this. One of the main purposes of the
     * class is to hide these element names.</i>
     *
     * This returns the internal element names "element0", "element1" and so on.
     * Exception: {@link hidden()} may have a custom name. This may be useful to
     * access $_POST variables or to create custom JavaScript handlers.
     *
     * Use an absolute (positive values starting with 0 for the first element)
     * or relative (negative values starting with -1 for the element added last)
     * <i>offset</i> to get the name of any previously added element. Defaults
     * to -1. Returns false on error.
     *
     * @param offset int
     * @return string
     */
    function getName($offset = -1)
    {
        $id = ($offset < 0) ? (count($this->_rows) + $offset) : $offset;
        return isset($this->_rows[$id]) ? $this->_rows[$id]['name'] : false;
    }

    /**
     * Adds an additional attribute to the form or any inner input element.
     *
     * Puts the attribute or JavaScript event handler to the form element added
     * last. If no element was created yet or <i>attribute</i> is "onsubmit" the
     * attribute will be added to the form itself.
     *
     * Some useful examples:
     *
     * <pre>$form->addAttribute("onfocus", "if (this.value == '...') this.value = '';");
     * $form->addAttribute("onblur", "if (this.value == '') this.value = '...';");
     * $form->addAttribute("onclick", "this.form.submit();");
     * $form->addAttribute(
     *     "onsubmit",
     *     "if (this.elements['element0'].value == '') {
     *          alert('Please enter something!');
     *          return false;
     *      }");</pre>
     *
     * @param attribute string
     * @param value string
     * @return string
     */
    function addAttribute($attribute, $value = null)
    {
        if (!isset($value)) $value = $attribute;
        // If there are still no elements, add the attribute or handler to the form.
        if (empty($this->_rows) || strcasecmp($attribute, "onsubmit") == 0)
        {
            $a = &$this->_attributes[$attribute];
        }
        else
        {
            // Add the attribute or event handler to the form element added last.
            $a = &$this->_rows[count($this->_rows) - 1]['attributes'][$attribute];
        }
        if (empty($a)) $a = ""; else $a .= " ";
        return $a .= $value;
    }

    /**
     * Adds a class to any outer input element template.
     *
     * Use <code>{class}</code> somewhere in one of the {@link templates}.
     *
     * @param class string
     * @return string
     */
    function addClass($class)
    {
        if (empty($this->_rows)) return false;
        $a = &$this->_rows[count($this->_rows) - 1]['class'];
        if (empty($a)) $a = ""; else $a .= " ";
        return $a .= $class;
    }

    /**
     * Adds an error message to an element.
     *
     * Sets an error to the element added last by {@link checkbox()},
     * {@link file()}, {@link password()}, {@link radio()}, {@link select()},
     * {@link submit()}, {@link text()} or {@link textarea()}. Use a negative or
     * positive (starting with 0 for the first element) <i>offset</i> to add an
     * error message to any previous element. Defaults to -1.
     *
     * The label of the faulty element will be replaced by the error message (if
     * present) and displayed using another {@link templates template} (if
     * present). If error() was called one or more times, {@link isValid()} will
     * return false.
     *
     * @param message string
     * @param offset int
     * @return void
     */
    function error($message = "", $offset = -1)
    {
        $id = ($offset < 0) ? (count($this->_rows) + $offset) : $offset;
        if (!isset($this->_rows[$id])) return false;
        // If there is already an error message don't replace it.
        if ($this->_isSubmitted && empty($this->_rows[$id]['error']))
        {
            $this->_rows[$id]['error'] = $message;

            // Always reset a password element if it got an error.
            if ($this->_rows[$id]['type'] == "password")
            {
                $this->_rows[$id]['value'] = "";
            }
            // Always reset a file upload element if it got an error.
            elseif ($this->_rows[$id]['type'] == "file")
            {
                @unlink($this->_rows[$id]['value']['tmp_name']);
                $this->_rows[$id]['value'] = false;
            }
            $this->_hasErrors = true;
        }
    }

    /**
     * Checks if the form is submitted error-free.
     *
     * Returns true if the form was submitted already and no error has been set.
     * If the form is displayed the first time, isValid() always returns false.
     * If {@link error()} was called at least once it returns false too.
     *
     * @return bool
     */
    function isValid()
    {
        return $this->_isSubmitted && !$this->_hasErrors;
    }

    /**
     * Compiles the form and returns the HTML output.
     *
     * Compiles the whole form using default or user defined {@link templates}
     * and returns the resulting HTML code as a string. Example:
     *
     * <code>echo $form->toHTML();</code>
     *
     * @param bool $setFocus
     * @return string
     */
    function toHTML($setFocus = null)
    {
        $form = '<form action="';
        $form .= isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] :
            $GLOBALS['HTTP_SERVER_VARS']['PHP_SELF'];
        if (!empty($_SERVER['QUERY_STRING']))
        {
            $form .= "?" . htmlspecialchars($_SERVER['QUERY_STRING']);
        }
        elseif (!empty($GLOBALS['HTTP_SERVER_VARS']['QUERY_STRING']))
        {
            $form .= "?" . htmlspecialchars($GLOBALS['HTTP_SERVER_VARS']['QUERY_STRING']);
        }
        if (!isset($this->anchor)) $form .= "#" . $this->id;
        elseif ($this->anchor)      $form .= "#" . $this->anchor;
        $form .= '"';
        if (!empty($this->_encType)) $form .= ' enctype="' . $this->_encType . '"';
        $form .= $this->_implodeAttributes($this->_attributes);
        $form .= ' id="' . $this->id . '" method="post">';
        $elements = "";

        reset($this->_rows);
        while (list($id, $row) = each($this->_rows))
        {
            $nameAttr = ' name="' . $row['name'] . '"';
            $idAttr = ' id="' . $row['name'] . 'i"';
            $genericAttr = $this->_implodeAttributes($row['attributes']);
            if (isset($row['help']) && is_string($row['help']))
            {
                $row['help'] = explode("\t", $row['help'], 2);
            }
            if (!empty($row['help'][0]))
            {
                $genericAttr .= ' title="' . str_replace('"', '&quot;',
                    strip_tags($row['help'][0])) . '"';
            }
            $input = '';
            switch ($row['type'])
            {
                case "header":
                    $elements .= str_replace(array("{header}", "{class}"),
                        array($row['header'], empty($row['class']) ? "" : ' class="' . $row['class'] . '"'),
                        $this->getTemplate('header', $row['name']));
                    continue 2;
                case "hidden":
                    // Dont use any templates for hidden elements; skip and continue.
                    $form .= '<input type="hidden"' . $nameAttr . ' value="' .
                        htmlspecialchars($this->magicQuotes ?
                        stripslashes($row['value']) : $row['value']) . '" />';
                    continue 2;
                case "static":
                    $input = nl2br(htmlspecialchars($row['value'])) .
                        '<input type="hidden"' . $nameAttr . ' value="' .
                        htmlspecialchars($this->magicQuotes ?
                        stripslashes($row['value']) : $row['value']) . '" />';
                    break;
                case "textarea":
                    $input = '<textarea' . $nameAttr;
                    $input .= ' cols="' . (empty($row['cols']) ? $this->size : $row['cols']) . '"';
                    $input .= ' rows="' . (empty($row['rows']) ? 3 : $row['rows']) . '"';
                    $input .= ' wrap="' . (empty($row['wrap']) ? "virtual" : $row['wrap']) . '"';
                    // Add an id for use in the label (added later).
                    $input .= $idAttr . $genericAttr . '>';
                    $input .= htmlspecialchars($this->magicQuotes ?
                        stripslashes($row['value']) : $row['value']);
                    $input .= '</textarea>';
                    break;
                case "select":
                    $input = '<select' . $nameAttr . ' size="' . $row['size'] . '"';
                    // Add an id for use in the label (added later).
                    $input .= $idAttr . $genericAttr . '>';
                    while (list($key, $value) = each($row['options']))
                    {
                        $input .= '<option value="';
                        $input .= htmlspecialchars($this->magicQuotes ?
                            stripslashes($key) : $key);
                        $input .= '"';
                        if (strcmp($key, $row['value']) == 0) $input .= ' selected="selected"';
                        $input .= '>' . str_replace("<", "&lt;", $value) . "</option>\n";
                    }
                    $input .= '</select>';
                    break;
                case "radio":
                case "checkbox":
                    $i = 0;
                    while (list($key, $value) = each($row['options']))
                    {
                        $input .= '<input type="' . $row['type'] . '" name="' . $row['name'];
                        // Name have to be an array when using multiple checkboxes.
                        if ($row['type'] == 'checkbox' && count($row['options']) > 1)
                        {
                            $input .= '[]';
                        }
                        $input .= '" value="' . htmlspecialchars(
                            $this->magicQuotes ? stripslashes($key) : $key);
                        $input .= '"';
                        // This works for a single value too; it will be casted to an array.
                        if (in_array($key, (array)$row['value']))
                        {
                            $input .= ' checked="checked"';
                        }
                        // Id attribute will be used in the label only.
                        $input .= ' id="' . $row['name'] . 'i' . $i . '"';
                        $input .= $genericAttr . ' />';
                        if ($value)
                        {
                            $input .= '<label for="' . $row['name'] . 'i' . $i . '"';
                            // Add an access key handler if there is an underlined character.
                            if (preg_match('/<u>(\w)/i', $value, $match))
                            {
                                $input .= ' accesskey="' . strtolower($match[1]) . '"';
                            }
                            $input .= ">" . $value . "</label>\n";
                        }
                        $i++;
                    }
                    break;
                case "submit":
                    while (list($i, $value) = each($row['value']))
                    {
                        $input .= '<input type="submit"' . $nameAttr;
                        // An input button may have a default value.
                        if (!empty($value))
                        {
                            $input .= ' value="' . strtr($this->magicQuotes ?
                                stripslashes($value) : $value, array('"' =>
                                '&quot;', '<' => '&lt;', '>' => '&gt;')) . '"';
                        }
                        if (count($row['value']) == 1) $input .= ' accesskey="s"';
                        $input .= $genericAttr . " />\n";
                    }
                    break;
                case "image":
                    while (list($i, $value) = each($row['value']))
                    {
                        $input .= '<input type="image"' . $nameAttr . ' src="' .
                            htmlspecialchars($this->magicQuotes ?
                            stripslashes($value) : $value) . '"' . $genericAttr
                            . " />\n";
                    }
                    break;
                case 'text':
                    $c = count($row['value']);
                    $maxes = (array)$row['maxLength'];
                    $sizes = (array)$row['size'];
                    foreach ((array)$row['value'] as $i => $value)
                    {
                        if (!isset($maxes[$i])) $maxes[$i] = $maxes[$i - 1];
                        if (!isset($sizes[$i])) $sizes[$i] = $sizes[$i - 1];
                        if ($input && isset($row['help'][1]))
                        {
                            $input .= current(array_splice($row['help'], 1, 1));
                        }
                        $input .= '<input type="' . $row['type'] . '" name="' . $row['name'];
                        // Name have to be an array on multiple text fields.
                        if ($c > 1) $input .= '[]';
                        $input .= '" value="';
                        $input .= htmlspecialchars($this->magicQuotes ?
                            stripslashes($value) : $value);
                        $input .= '" maxlength="' . ($maxes[$i] ? $maxes[$i] :
                            $this->maxLength) . '"';
                        $size = (int)round($this->size / $c);
                        // Don't make the element larger than needed, if no size is given.
                        if (!empty($maxes[$i]) && $maxes[$i] < $size)
                        {
                            $size = $maxes[$i];
                        }
                        if ($sizes[$i]) $size = $sizes[$i];
                        $input .= ' size="' . $size . '"';
                        // Add an id for use in the label (added later).
                        $input .= ' id="' . $row['name'] . 'i' . ($c > 1 ? $i : '') . '"';
                        $input .= $genericAttr . ' />';
                    }
                    break;
                default:
                    $input = '<input type="' . $row['type'] . '"' . $nameAttr;
                    // Needed because some elements did not have or allow a value.
                    if (isset($row['value']) && $row['type'] != "file")
                    {
                        $input .= ' value="';
                        $input .= htmlspecialchars($this->magicQuotes ?
                            stripslashes($row['value']) : $row['value']);
                        $input .= '"';
                    }
                    // A file upload element did not have a maximum length at all.
                    if (isset($row['maxLength']))
                    {
                        $input .= ' maxlength="' . ($row['maxLength'] ?
                            $row['maxLength'] : $this->maxLength) . '"';
                    }
                    // Use default width if it is set but zero.
                    if (isset($row['size']))
                    {
                        // The file element have its own button so make it smaller.
                        $size = $this->size - ($row['type'] == "file" ? 22 : 0);
                        // Don't make the element larger than needed, if no size is given.
                        if (!empty($row['maxLength']) && $row['maxLength'] < $size)
                        {
                            $size = $row['maxLength'];
                        }
                        if ($row['size']) $size = $row['size'];
                        // Make the field a lot smaller if it already contains a file.
                        if ($row['type'] == "file" && !empty($row['value'])) $size = 5;
                        $input .= ' size="' . $size . '"';
                    }
                    // Add an id for use in the label (added later).
                    $input .= $idAttr . $genericAttr . ' />';

                    // Hidden element with meta data of files already uploaded.
                    if ($row['type'] == "file" && !empty($row['value']))
                    {
                        // Display the file name already submitted before.
                        $name = $row['value']['name'];
                        $length = max($this->size - 32, 12);
                        if (strlen($name) > $length)
                        {
                            $name = substr($name, 0, $length) . '...';
                        }
                        $input = $name . ' ' . $input;
                        $input .= '<input type="hidden" name="' . $row['name'] . 'h"';
                        // Serialized array contains: tmp_name, name, type, size.
                        $input .= ' value="' .
                            htmlspecialchars(serialize($row['value'])) . '" />';
                    }
            }

            if (!empty($row['help'][1])) $input .= $row['help'][1];
            $element = str_replace("{input}", $input, $this->getTemplate('input', $row['name']));

            // If there is no {error} tag in the template but an error.
            if (isset($row['error']) && strpos($element, "{error}") === false)
            {
                // Save the labels access key, if the error message have no own.
                if (!stristr($row['error'], "<u>") &&
                    preg_match('/<u>(\w)/i', @$row['label'], $match))
                {
                    $row['error'] = preg_replace('/' . $match[1] . '/i',
                        '<u>\0</u>', $row['error'], 1);
                }
                // Make the label an error if no other error message was given.
                if (empty($row['error'])) $row['error'] = @$row['label'];
                $row['label'] = "";
            }

            // If there is no <label> tag in this element but an id, add one.
            if (!(empty($row['label']) && empty($row['error'])) &&
                strpos($input, "<label") === false &&
                preg_match('/id="([^"]+)/', $input, $mId))
            {
                $labelTag = '<label';
                // Add an accesskey handler if there is an underlined character.
                if (preg_match('/<u>(\w)/i', @$row['label'] . @$row['error'], $mKey))
                {
                    $labelTag .= ' accesskey="' . strtolower($mKey[1]) . '"';
                }
                $labelTag .= ' for="' . $mId[1] . '">';
                $element = str_replace("{label}", $labelTag . "{label}</label>",
                    $element);
            }

            if (isset($row['error']) && strpos($element, "{error}") === false)
            {
                $element = str_replace("{label}", "{error}", $element);
            }

            // Load all the tiny sub templates if needed and if exists.
            if (!empty($row['label']) && $this->getTemplate('label', $row['name']))
            {
                $element = str_replace("{label}", $this->getTemplate('label', $row['name']),
                    $element);
            }
            if (!empty($row['error']) && $this->getTemplate('error', $row['name']))
            {
                $element = str_replace("{error}", $this->getTemplate('error', $row['name']),
                    $element);
            }
            if (!empty($row['help'][0]) && $this->getTemplate('help', $row['name']))
            {
                $element = str_replace("{help}", $this->getTemplate('help', $row['name']),
                    $element);
            }

            $elements .= str_replace(
                array("{label}", "{error}", "{help}", "{class}"),
                array(@$row['label'], @$row['error'], @$row['help'][0],
                empty($row['class']) ? "" : ' class="' . $row['class'] . '"'),
                $element);
        }

        // Do a global search and replace on underlined accesskey characters.
        if ($this->getTemplate('accesskey'))
        {
            $replacement = str_replace("{accesskey}", '\1', $this->getTemplate('accesskey'));
            $elements = preg_replace('/<u>(\w)<\/u>/i', $replacement, $elements);
        }

        $form .= str_replace("{input}", $elements, $this->getTemplate('form'));
        $form .= '</form>';
        if ((!isset($setFocus) && $this->id == "form") || !empty($setFocus))
        {
            $form .= $this->_getFocusHandler();
        }
        // Returns the whole compiled web form.
        return $form;
    }

    /**
     * Internal method which returns one of the templates.
     *
     * This is an interface you can replace using an extended class to return
     * more than one different <code>'input'</code> {@link templates template}
     * for example.
     *
     * @param template string One of the templates names.
     * @param name string Element name currently rendered.
     * @return string
     */
    function getTemplate($template, $name = null)
    {
        return empty($this->templates[$template]) ? false : $this->templates[$template];
    }

    /**
     * @param attributes array
     * @return string
     * @access private
     */
    function _implodeAttributes(&$attributes)
    {
        if (empty($attributes)) return "";
        $html = "";
        foreach ($attributes as $attribute => $value)
        {
            // Compile the HTML output of this attribute or JavaScript handler.
            $html .= ' ' . $attribute . '="' . str_replace('"', '&quot;', $value) . '"';
        }
        return $html;
    }

    /**
     * @return string
     * @access private
     */
    function _getFocusHandler()
    {
        $form = "";
        $id = null;
        $count = count($this->_rows);
        for ($i = 0; $i < $count; $i++)
        {
            // Search for the first text element. Focus makes only sense there.
            if ($this->_rows[$i]['type'] != 'text' &&
                $this->_rows[$i]['type'] != 'password' &&
                $this->_rows[$i]['type'] != 'textarea') continue;
            if (!isset($id)) $id = $i;
            // Search for the first text element containing an error.
            if (!empty($this->_rows[$i]['error'])) { $id = $i; break; }
        }
        if (isset($id))
        {
            // Set the focus on the first element using JavaScript.
            $form = "<script type=\"text/javascript\">\nself.onload=";
            $form .= "function(){var f=document.forms['" . $this->id . "'];";
            $form .= "if(f){var e=f.elements['" . $this->_rows[$id]['name'];
            // Handle multiple text elements too.
            $form .= count($this->_rows[$id]['value']) > 1 ? "[]'][0" : "'";
            $form .= "];if(e&&e.focus)e.focus();}}\n</script>";
        }
        return $form;
    }

    /**
     * Outputs the form.
     *
     * Displays the whole compiled form. Set <i>setFocus</i> to disable or
     * enable the auto-focus feature (enabled by default if no id was set for
     * the form, disabled otherwise). A slightly complex example:
     *
     * <pre><?php
     * require_once("Apeform.class.php");
     * $form = new Apeform();
     * $text = $form->text("Something");
     * if (!$text) $form->error("Please enter something");
     * $form->submit();
     * if ($form->isValid()) echo "Thank you!";
     * else $form->display();
     * ?></pre>
     *
     * @param bool $setFocus
     * @return void
     */
    function display($setFocus = null)
    {
        echo $this->toHTML($setFocus);
    }
}
