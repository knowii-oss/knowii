import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { BlogPostQuote } from './blog-post-quote';

const Story: ComponentMeta<typeof BlogPostQuote> = {
  component: BlogPostQuote,
  title: 'BlogPostQuote',
};
export default Story;

const Template: ComponentStory<typeof BlogPostQuote> = (args) => <BlogPostQuote {...args}>Foo bar baz</BlogPostQuote>;

export const Primary = Template.bind({});
Primary.args = {};
