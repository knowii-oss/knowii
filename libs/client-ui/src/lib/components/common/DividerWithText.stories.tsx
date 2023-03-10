import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { DividerWithText } from './DividerWithText';

const Story: ComponentMeta<typeof DividerWithText> = {
  component: DividerWithText,
  title: 'DividerWithText',
};
export default Story;

const Template: ComponentStory<typeof DividerWithText> = (args) => <DividerWithText {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
