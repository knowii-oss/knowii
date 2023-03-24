import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { BlogPostSignature } from './blog-post-signature';

const Story: ComponentMeta<typeof BlogPostSignature> = {
  component: BlogPostSignature,
  title: 'BlogPostSignature',
};
export default Story;

const Template: ComponentStory<typeof BlogPostSignature> = (args) => <BlogPostSignature {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
