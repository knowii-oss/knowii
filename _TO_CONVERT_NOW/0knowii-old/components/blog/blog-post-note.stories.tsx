import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { BlogPostNote } from './blog-post-note';

const Story: ComponentMeta<typeof BlogPostNote> = {
  component: BlogPostNote,
  title: 'BlogPostNote',
};
export default Story;

const Template: ComponentStory<typeof BlogPostNote> = (args) => <BlogPostNote {...args}>Foo bar baz</BlogPostNote>;

export const Primary = Template.bind({});
Primary.args = {};
