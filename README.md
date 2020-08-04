# ANSIColor

A basic class that makes adding ANSI Standard colors and some peripheral ANSI escape codes simpler by using markup tags.

## Getting Started

Include the class in your project:

include_once('/path/to/ANSIColor.class.php');

### Prerequisites

It's best to have multibyte string functions compiled in your version of PHP, but not necessary.

### Examples

Outputting colored foreground text:

```
echo ANSIColor::parse('<ansi fg="red">RED <ansi fg="green bold="true">(GREEN NESTED)</ansi> Text</ansi>');
```

Outputting colored foreground AND background text:

```
echo ANSIColor::parse('<ansi fg="red" bg="white" bold="true" boldbg="true">Some Text</ansi>');
```

Getting the actual length of text:

```
echo ANSIColor::strlen('<ansi fg="red">RED <ansi fg="green bold="true">(GREEN NESTED)</ansi> Text</ansi>');
```

Underlining Text:

```
echo ANSIColor::parse('This is <ansi underline="true">important</ansi>!!!');
```

Padding Text:

```
echo ANSIColor::parse('<ansi pad_right="50">Text padded right</ansi>');
echo ANSIColor::parse('<ansi pad_left="50">Text padded left</ansi>');
```
Drawing Text to a specific X/Y position on screen:

```
echo ANSIColor::parse('<ansi pos="50,25">This is written to x25 and y50</ansi>');
```

Clearing the screen:

```
echo ANSIColor::parse('<ansi clear="true"></ansi>');
```

Clearing the screen AND scrollback buffer:

```
echo ANSIColor::parse('<ansi clear="all"></ansi>');
```

Special functionality:

Overriding the default assumption of 80 columns wide:

```
ANSIColor::parse('<ansi width="200"></ansi>');
```

Centering Text. Will center to an assumped 80 columns wide unless overridden at some point using the width="###" param:

```
echo ANSIColor::parse('<ansi pad_left="50">Text Centered</ansi>');
```

## Authors

* **Dylan Squires** 


