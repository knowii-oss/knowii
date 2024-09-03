import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { BlogPostWarning } from './blog-post-warning';

const Story: ComponentMeta<typeof BlogPostWarning> = {
  component: BlogPostWarning,
  title: 'BlogPostWarning',
};
export default Story;

const Template: ComponentStory<typeof BlogPostWarning> = (args) => <BlogPostWarning {...args}>Foo bar baz</BlogPostWarning>;

export const Primary = Template.bind({});
Primary.args = {};
