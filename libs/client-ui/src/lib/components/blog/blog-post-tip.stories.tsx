import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { BlogPostTip } from './blog-post-tip';

const Story: ComponentMeta<typeof BlogPostTip> = {
  component: BlogPostTip,
  title: 'BlogPostTip',
};
export default Story;

const Template: ComponentStory<typeof BlogPostTip> = (args) => <BlogPostTip {...args}>Foo bar baz</BlogPostTip>;

export const Primary = Template.bind({});
Primary.args = {};
